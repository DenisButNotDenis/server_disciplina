<?php

namespace App\Http\DTOs\Messenger;

use Spatie\LaravelData\Data;
use Carbon\Carbon;
use App\Http\DTOs\User\UserResourceDTO; 

/**
 * DTO для представления связи пользователя с мессенджером.
 * (Пункт 9)
 */
final readonly class UserMessengerResourceDTO extends Data
{
    public function __construct(
        public int $id,
        public int $userId,
        public int $messengerId,
        public string $messengerName,
        public string $messengerUserId, 
        public bool $isConfirmed,
        public ?Carbon $confirmedAt,
        public bool $allowNotifications,
        public Carbon $createdAt,
        public Carbon $updatedAt,
    ) {}

    /**
     * Создает экземпляр DTO из объекта модели UserMessenger.
     *
     * @param \App\Models\UserMessenger $userMessenger Модель UserMessenger.
     * @return self
     */
    public static function fromModel(\App\Models\UserMessenger $userMessenger): self
    {
        // Убедимся, что мессенджер загружен для получения имени
        if (!$userMessenger->relationLoaded('messenger')) {
            $userMessenger->load('messenger');
        }

        return new self(
            id: $userMessenger->id,
            userId: $userMessenger->user_id,
            messengerId: $userMessenger->messenger_id,
            messengerName: $userMessenger->messenger->name, 
            messengerUserId: $userMessenger->messenger_user_id,
            isConfirmed: $userMessenger->is_confirmed,
            confirmedAt: $userMessenger->confirmed_at,
            allowNotifications: $userMessenger->allow_notifications,
            createdAt: $userMessenger->created_at,
            updatedAt: $userMessenger->updated_at,
        );
    }
}
