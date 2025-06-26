<?php

namespace App\Http\DTOs\Auth;

use Carbon\Carbon; // Нужно, так как birthday - это объект Carbon

class RegisterResourceDTO
{
    public function __construct(
        public string $username,
        public string $email,
        public string $password,
        public Carbon $birthday,
    ) {}

    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->password,
            'birthday' => $this->birthday->format('Y-m-d'), // Форматируем дату обратно в строку для JSON
        ];
    }
}