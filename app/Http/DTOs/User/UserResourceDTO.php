<?php

namespace App\Http\DTOs\User;

use Spatie\LaravelData\Data;
use Carbon\Carbon;
use App\Http\DTOs\Role\RoleCollectionDTO; // Импортируем DTO для коллекции ролей

final readonly class UserResourceDTO extends Data
{
    public function __construct(
        public int $id,
        public string $username,
        public string $email,
        public ?Carbon $birthday,
        public Carbon $createdAt,
        public Carbon $updatedAt,
        public ?Carbon $deletedAt = null, // Добавляем, если пользователь может быть мягко удален
        public RoleCollectionDTO $roles, // Коллекция ролей пользователя
    ) {}

    /**
     * Создает экземпляр DTO из объекта модели User.
     *
     * @param \App\Models\User $user Модель User.
     * @return self
     */
    public static function fromModel(\App\Models\User $user): self
    {
        // Убедимся, что роли загружены. Если они не были загружены ранее (например, через User::with('roles')->get()),
        // то метод load() загрузит их.
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
            deletedAt: $user->deleted_at, // Передаем deleted_at
            roles: RoleCollectionDTO::collect($user->roles), // Передаем коллекцию ролей в DTO
        );
    }
}
