<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Messenger;
use App\Models\UserMessenger;
use App\Models\NotificationLog; // Импортируем модель для логов уведомлений
use App\Services\Messenger\MessengerService; // Импортируем базовый сервис мессенджера
use Illuminate\Queue\Attributes\WithCappedAttempts; // Для ограничения количества попыток (Пункт 14, 15)
use Illuminate\Queue\Attributes\WithTimeout; // Для установки таймаута выполнения

#[WithCappedAttempts(attempts: config('messengers.notification_retries.max_attempts'), decayMinutes: config('messengers.notification_retries.retry_delay_seconds') / 60)] // Пункт 14, 15
#[WithTimeout(60)] // Максимум 60 секунд на выполнение одной попытки отправки
class SendMessengerNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $userId;
    public int $messengerId;
    public int $userMessengerId; // ID связки UserMessenger
    public string $messageContent;
    public ?string $verificationCode; // Для случая отправки кода подтверждения

    /**
     * The number of times the job may be attempted.
     * @var int
     */
    public int $tries; // Пункт 14

    /**
     * The number of seconds to wait before retrying the job.
     * @var int
     */
    public int $backoff; // Пункт 14

    /**
     * Создает новый экземпляр задачи.
     * (Пункт 13)
     *
     * @param int $userId ID пользователя-получателя.
     * @param int $messengerId ID мессенджера.
     * @param int $userMessengerId ID связки пользователя с мессенджером.
     * @param string $messageContent Содержимое сообщения.
     * @param string|null $verificationCode Код подтверждения (если применимо).
     */
    public function __construct(
        int $userId,
        int $messengerId,
        int $userMessengerId,
        string $messageContent,
        ?string $verificationCode = null
    ) {
        $this->userId = $userId;
        $this->messengerId = $messengerId;
        $this->userMessengerId = $userMessengerId;
        $this->messageContent = $messageContent;
        $this->verificationCode = $verificationCode;

        // Настраиваем количество попыток и задержку из конфига (Пункт 15)
        $this->tries = config('messengers.notification_retries.max_attempts');
        $this->backoff = config('messengers.notification_retries.retry_delay_seconds');

        Log::info("Notification Job [{$this->job->getJobId()}] created for user {$userId}, messenger {$messengerId}.");
    }

    /**
     * Выполняет фоновую задачу по отправке уведомления.
     * (Пункт 13)
     */
    public function handle(): void
    {
        Log::info("Notification Job [{$this->job->getJobId()}] started handling. Attempt: {$this->attempts()}.");

        /** @var User|null $user */
        $user = User::find($this->userId);
        /** @var Messenger|null $messenger */
        $messenger = Messenger::find($this->messengerId);
        /** @var UserMessenger|null $userMessenger */
        $userMessenger = UserMessenger::find($this->userMessengerId);

        if (!$user || !$messenger || !$userMessenger) {
            Log::error("Notification Job failed: User, Messenger, or UserMessenger link not found. User ID: {$this->userId}, Messenger ID: {$this->messengerId}, UserMessenger ID: {$this->userMessengerId}");
            $this->fail(new \Exception('Dependent model not found.'));
            return;
        }

        // Проверяем, разрешены ли уведомления для этой связки (Пункт 7.b.vi)
        if (!$userMessenger->allow_notifications) {
            Log::info("Notification skipped for User {$this->userId} via Messenger {$this->messengerId}: notifications are disabled for this link.");
            // Если уведомления отключены, мы не должны повторять попытку
            $this->markAsSent('skipped', 'Notifications disabled by user.');
            return;
        }

        // Проверяем, подтверждена ли связка (Пункт 7.b.iv)
        if (!$userMessenger->is_confirmed) {
            Log::warning("Notification failed for User {$this->userId} via Messenger {$this->messengerId}: link not confirmed.");
            // Если связка не подтверждена, это не временная ошибка, не повторяем.
            $this->markAsSent('failed', 'Messenger link not confirmed.');
            return;
        }

        // Получаем сервис мессенджера из контейнера
        /** @var MessengerService $messengerService */
        $messengerService = app(MessengerService::class, ['name' => $messenger->name]);

        $success = false;
        $errorMessage = null;

        try {
            $success = $messengerService->sendMessage(
                $userMessenger->messenger_user_id, // Идентификатор пользователя в мессенджере (например, chat_id)
                $this->messageContent
            );
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            Log::error("Error sending message via {$messenger->name}: {$errorMessage}");
        }

        if ($success) {
            $this->markAsSent('sent');
            Log::info("Notification Job [{$this->job->getJobId()}] successfully sent message for user {$this->userId}.");
        } else {
            $this->markAsSent('failed', $errorMessage); // Логируем как failed, но job может быть перепланирован
            Log::error("Notification Job [{$this->job->getJobId()}] failed to send message for user {$this->userId}. Error: {$errorMessage}");

            // Если отправка не удалась, и это не последняя попытка (Пункт 14)
            if ($this->attempts() < $this->tries) {
                // Laravel автоматически перепланирует job с задержкой, если он не `succeeded()` или не `failed()`.
                // Здесь мы можем явно бросить исключение, чтобы Laravel знал о неудаче и перепланировал.
                throw new \Exception("Failed to send notification. Retrying...");
            } else {
                Log::critical("Notification Job [{$this->job->getJobId()}] exhausted all attempts for user {$this->userId}.");
                // Можно отправить дополнительное уведомление администратору о критической ошибке
            }
        }
    }

    /**
     * Создает или обновляет запись в логе уведомлений.
     * (Пункт 16)
     *
     * @param string $status
     * @param string|null $errorMessage
     */
    protected function markAsSent(string $status, ?string $errorMessage = null): void
    {
        // Попытка найти существующий лог для текущей задачи, если она была перепланирована
        // или создать новую запись. Для простоты, всегда создаем новую запись для каждой попытки
        NotificationLog::create([
            'user_id' => $this->userId,
            'messenger_id' => $this->messengerId,
            'message_content' => $this->messageContent,
            'status' => $status,
            'attempt_number' => $this->attempts(),
            'error_message' => $errorMessage,
        ]);
        Log::info("Notification log for user {$this->userId}, messenger {$this->messengerId} recorded with status '{$status}' at attempt {$this->attempts()}.");
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Notification Job [{$this->job->getJobId()}] completely failed after {$this->attempts()} attempts. Reason: " . $exception->getMessage());

    }
}
