<?php

namespace App\Http\DTOs\Report;

use Spatie\LaravelData\Data;
use Carbon\Carbon;
use App\Http\DTOs\User\UserResourceDTO; // Если нужно вложенный DTO пользователя
use App\Http\DTOs\Messenger\MessengerResourceDTO; // Если нужно вложенный DTO мессенджера

/**
 * DTO для представления одной записи лога уведомлений в отчете.
 * (Пункт 17)
 */
final readonly class NotificationReportResourceDTO extends Data
{
    public function __construct(
        public int $id,
        public ?int $userId,
        public ?string $username, // Добавлено для удобства отчета
        public ?int $messengerId,
        public ?string $messengerName, // Добавлено для удобства отчета
        public string $messageContent,
        public string $status,
        public int $attemptNumber,
        public ?string $errorMessage,
        public Carbon $createdAt,
    ) {}

    /**
     * Создает экземпляр DTO из объекта модели NotificationLog.
     *
     * @param \App\Models\NotificationLog $log Модель NotificationLog.
     * @return self
     */
    public static function fromModel(\App\Models\NotificationLog $log): self
    {
        // Убедимся, что связанные модели загружены для получения имен
        if (!$log->relationLoaded('user')) {
            $log->load('user');
        }
        if (!$log->relationLoaded('messenger')) {
            $log->load('messenger');
        }

        return new self(
            id: $log->id,
            userId: $log->user_id,
            username: $log->user?->username, // Безопасное обращение
            messengerId: $log->messenger_id,
            messengerName: $log->messenger?->name, // Безопасное обращение
            messageContent: $log->message_content,
            status: $log->status,
            attemptNumber: $log->attempt_number,
            errorMessage: $log->error_message,
            createdAt: $log->created_at,
        );
    }
}