<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Messenger;
use App\Models\UserMessenger;
use App\Http\Requests\Messenger\AttachUserMessengerRequest;
use App\Http\Requests\Messenger\VerifyUserMessengerRequest;
use App\Http\Requests\Messenger\ToggleUserMessengerNotificationsRequest;
use App\Http\DTOs\Messenger\UserMessengerResourceDTO;
use App\Http\DTOs\Messenger\UserMessengerCollectionDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use App\Services\Messenger\MessengerService; // Импортируем базовый сервис мессенджера
use Illuminate\Support\Facades\DB; // Для транзакций
use App\Jobs\SendMessengerNotificationJob; // Для отправки уведомлений (Пункт 13)

/**
 * Контроллер для управления связями User-Messenger.
 * (Пункт 9)
 */
class UserMessengerController extends Controller
{
    /**
     * Получить список мессенджеров, прикрепленных к пользователю.
     * (Пункт 9)
     */
    public function index(User $user): JsonResponse
    {
        // Проверка разрешения: пользователь может просматривать свои связи ИЛИ админ может просматривать связи любого пользователя.
        if (Auth::check()) {
            if (Auth::id() === $user->id) {
                // Разрешено для своих данных
            } elseif (Auth::user()->hasPermission('get-list-user-messenger')) {
                // Разрешено по разрешению
            } else {
                throw new AccessDeniedHttpException('Недостаточно прав для просмотра связей мессенджеров этого пользователя.');
            }
        } else {
            throw new AccessDeniedHttpException('Недостаточно прав для просмотра связей мессенджеров этого пользователя.');
        }

        $userMessengers = $user->messengersRelation()->with('messenger')->get(); // Загружаем мессенджер для DTO
        return response()->json(UserMessengerCollectionDTO::collect($userMessengers)->toArray(), 200);
    }

    /**
     * Прикрепить мессенджер к пользователю.
     * (Пункт 9)
     *
     * @param User $user Модель пользователя, к которому прикрепляем.
     * @param AttachUserMessengerRequest $request Запрос с данными для прикрепления.
     * @return JsonResponse
     */
    public function attach(User $user, AttachUserMessengerRequest $request): JsonResponse
    {
        // Авторизация проверена в AttachUserMessengerRequest::authorize()
        $validatedData = $request->validated();

        // Проверяем, существует ли мессенджер
        $messenger = Messenger::find($validatedData['messenger_id']);
        if (!$messenger) {
            throw new ModelNotFoundException('Messenger not found.');
        }

        // Пункт 6.b: Проверяем, что мессенджер соответствует среде окружения
        if ($messenger->environment !== config('app.env')) {
            throw new BadRequestHttpException('Невозможно прикрепить мессенджер, который не предназначен для текущей среды окружения.');
        }

        // Проверяем, не существует ли уже такая связка
        $existingLink = UserMessenger::where('user_id', $user->id)
                                    ->where('messenger_id', $messenger->id)
                                    ->where('messenger_user_id', $validatedData['messenger_user_id'])
                                    ->first();

        if ($existingLink) {
            throw new BadRequestHttpException('Эта связь пользователя с мессенджером уже существует.');
        }

        // Создаем новую запись связки
        $userMessenger = DB::transaction(function () use ($user, $messenger, $validatedData) {
            return UserMessenger::create([
                'user_id' => $user->id,
                'messenger_id' => $messenger->id,
                'messenger_user_id' => $validatedData['messenger_user_id'],
                'is_confirmed' => false, // Изначально не подтверждено (Пункт 7.b.iv)
                'allow_notifications' => $validatedData['allow_notifications'] ?? true, // Пункт 7.b.vi
            ]);
        });

        // Отправляем код подтверждения через мессенджер (Пункт 10, 11)
        // Для Telegram, messenger_user_id - это chat_id, который нужно подтвердить.
        // Здесь мы просто отправляем "код" (которым является сам chat_id)
        // в мессенджер, чтобы пользователь мог его "ввести" на следующем шаге verify.
        /** @var MessengerService $messengerService */
        $messengerService = app(MessengerService::class, ['name' => $messenger->name]);
        $verificationCode = $validatedData['messenger_user_id']; // В Telegram это сам chat_id
        $message = "Для подтверждения вашей связи с системой, пожалуйста, используйте этот код: <b>{$verificationCode}</b>. Вы можете ввести его на странице подтверждения в приложении.";

        // Отправляем уведомление в фоновом режиме (Пункт 13)
        SendMessengerNotificationJob::dispatch($user->id, $messenger->id, $userMessenger->id, $message, $verificationCode)
            ->onQueue('notifications'); // Отправляем в специальную очередь уведомлений

        Log::info("User {$user->id} attached messenger {$messenger->name}. Confirmation message dispatched.");

        $userMessenger->load('messenger'); // Загружаем мессенджер для DTO
        return response()->json([
            'message' => 'Messenger attached successfully. Please check your messenger to confirm the connection with the provided code.',
            'user_messenger' => UserMessengerResourceDTO::fromModel($userMessenger)->toArray(),
        ], 201);
    }

    /**
     * Открепить мессенджер от пользователя.
     * (Пункт 9)
     *
     * @param User $user Модель пользователя.
     * @param UserMessenger $userMessenger Модель связи UserMessenger.
     * @return JsonResponse
     */
    public function detach(User $user, UserMessenger $userMessenger): JsonResponse
    {
        // Проверка разрешения: пользователь может открепить свою связь ИЛИ админ может открепить любую связь.
        if (Auth::check()) {
            if (Auth::id() === $user->id && $userMessenger->user_id === Auth::id()) {
                // Разрешено для своих данных
            } elseif (Auth::user()->hasPermission('detach-user-messenger')) {
                // Разрешено по разрешению
            } else {
                throw new AccessDeniedHttpException('Недостаточно прав для открепления этого мессенджера.');
            }
        } else {
            throw new AccessDeniedHttpException('Недостаточно прав для открепления этого мессенджера.');
        }

        // Проверяем, что связь принадлежит указанному пользователю
        if ($userMessenger->user_id !== $user->id) {
            throw new ModelNotFoundException('The specified messenger link does not belong to this user.');
        }

        DB::transaction(function () use ($userMessenger) {
            $userMessenger->delete();
        });

        Log::info("UserMessenger link {$userMessenger->id} (user {$user->id}, messenger {$userMessenger->messenger_id}) detached.");

        return response()->json(['message' => 'Messenger detached successfully.'], 200);
    }

    /**
     * Подтверждает связь пользователя с мессенджером.
     * (Пункт 10, 11)
     *
     * @param User $user Модель пользователя.
     * @param UserMessenger $userMessenger Модель связи UserMessenger.
     * @param VerifyUserMessengerRequest $request Запрос с кодом подтверждения.
     * @return JsonResponse
     */
    public function verify(User $user, UserMessenger $userMessenger, VerifyUserMessengerRequest $request): JsonResponse
    {
        // Авторизация проверена в VerifyUserMessengerRequest::authorize()
        $verificationCode = $request->validated('verification_code');

        // Проверяем, что связь принадлежит указанному пользователю и еще не подтверждена
        if ($userMessenger->user_id !== $user->id || $userMessenger->is_confirmed) {
            throw new BadRequestHttpException('Неверная связь или она уже подтверждена.');
        }

        // Получаем сервис мессенджера по имени
        /** @var MessengerService $messengerService */
        $messengerService = app(MessengerService::class, ['name' => $userMessenger->messenger->name]);

        // Пункт 11: Подтверждение обязательно осуществляется с помощью api соответствующего мессенджера.
        // Здесь вызывается метод сервиса для реальной или симулированной проверки.
        $isVerified = $messengerService->verifyConnection($userMessenger->messenger_user_id, $verificationCode);

        if (!$isVerified) {
            Log::warning("Failed to verify messenger connection for user {$user->id} with messenger {$userMessenger->messenger->name}. Invalid verification code or API issue.");
            return response()->json(['message' => 'Неверный код подтверждения или не удалось проверить связь с мессенджером.'], 400);
        }

        // Если подтверждено, обновляем статус в базе данных
        DB::transaction(function () use ($userMessenger) {
            $userMessenger->update([
                'is_confirmed' => true,
                'confirmed_at' => Carbon::now(),
            ]);
        });

        Log::info("UserMessenger link {$userMessenger->id} (user {$user->id}, messenger {$userMessenger->messenger->name}) confirmed.");

        $userMessenger->load('messenger'); // Загружаем мессенджер для DTO
        return response()->json([
            'message' => 'Связь с мессенджером успешно подтверждена.',
            'user_messenger' => UserMessengerResourceDTO::fromModel($userMessenger)->toArray(),
        ], 200);
    }

    /**
     * Изменяет флаг разрешения уведомлений для связи мессенджера пользователя.
     * (Пункт 7.b.vi)
     *
     * @param User $user Модель пользователя.
     * @param UserMessenger $userMessenger Модель связи UserMessenger.
     * @param ToggleUserMessengerNotificationsRequest $request Запрос с флагом.
     * @return JsonResponse
     */
    public function toggleNotifications(User $user, UserMessenger $userMessenger, ToggleUserMessengerNotificationsRequest $request): JsonResponse
    {
        // Авторизация проверена в ToggleUserMessengerNotificationsRequest::authorize()

        // Проверяем, что связь принадлежит указанному пользователю
        if ($userMessenger->user_id !== $user->id) {
            throw new ModelNotFoundException('The specified messenger link does not belong to this user.');
        }

        DB::transaction(function () use ($userMessenger, $request) {
            $userMessenger->update([
                'allow_notifications' => $request->validated('allow_notifications'),
            ]);
        });

        Log::info("UserMessenger link {$userMessenger->id} (user {$user->id}, messenger {$userMessenger->messenger->name}) notification status changed to {$request->validated('allow_notifications')}.");

        $userMessenger->load('messenger'); // Загружаем мессенджер для DTO
        return response()->json([
            'message' => 'Статус уведомлений для мессенджера успешно обновлен.',
            'user_messenger' => UserMessengerResourceDTO::fromModel($userMessenger)->toArray(),
        ], 200);
    }
}
