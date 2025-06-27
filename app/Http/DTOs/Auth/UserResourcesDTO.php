<?php

namespace App\Http\DTOs\Auth;

use Spatie\LaravelData\Data;
use Carbon\Carbon;
use App\Http\DTOs\Role\RoleCollectionDTO; // Для ЛР3, если используется

/**
 * Класс DTO для вывода информации о пользователе.
 */
final readonly class UserResourceDTO extends Data
{
    public function __construct(
        public int $id,
        public string $username,
        public string $email,
        public ?Carbon $birthday,
        public Carbon $createdAt,
        public Carbon $updatedAt,
        public ?Carbon $deletedAt = null,
        public RoleCollectionDTO $roles, // Для ЛР3, если используется
        public bool $isTwoFactorEnabled, // Новое поле для статуса 2FA (Пункт 18)
    ) {}

    /**
     * Создает экземпляр DTO из объекта модели User.
     *
     * @param \App\Models\User $user Модель User.
     * @return self
     */
    public static function fromModel(\App\Models\User $user): self
    {
        // Загружаем роли, если они не были загружены (для ЛР3)
        if (!$user->relationLoaded('roles')) {
            $user->load('roles');
        }

        return new self(
            id: $user->id,
            username: $user->username,
            email: $user->email,
            birthday: $user->birthday,
            createdAt: $user->created_at,
            updatedAt: $user->updated_at,
            deletedAt: $user->deleted_at,
            roles: RoleCollectionDTO::collect($user->roles), // Для ЛР3
            isTwoFactorEnabled: $user->is_2fa_enabled, // Передаем статус 2FA
        );
    }
}