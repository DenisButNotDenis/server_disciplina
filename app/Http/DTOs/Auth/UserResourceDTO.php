<?php

namespace App\Http\DTOs\Auth;

use App\Models\User; // Нужна модель User
use Carbon\Carbon;

class UserResourceDTO
{
    public function __construct(
        public int $id,
        public string $username,
        public string $email,
        public ?Carbon $birthday,
        public ?Carbon $emailVerifiedAt = null, // ? означает, что поле может быть null
        public Carbon $createdAt,
        public Carbon $updatedAt
    ) {}

    /**
     * Статический метод для создания DTO из модели User.
     */
    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            username: $user->username,
            email: $user->email,
            birthday: $user->birthday,
            emailVerifiedAt: $user->email_verified_at,
            createdAt: $user->created_at,
            updatedAt: $user->updated_at
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'birthday' => $this->birthday->format('Y-m-d'),
            'email_verified_at' => $this->emailVerifiedAt?->toDateTimeString(), // Если null, то не форматируем
            'created_at' => $this->createdAt->toDateTimeString(),
            'updated_at' => $this->updatedAt->toDateTimeString(),
        ];
    }
}