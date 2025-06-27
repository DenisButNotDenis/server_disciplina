<?php

namespace App\Http\DTOs\Auth;

class LoginResourceDTO
{
    // Конструктор - это функция, которая вызывается, когда создается новый объект DTO.
    // Public свойства автоматически станут свойствами объекта.
    public function __construct(
        public string $username,
        public string $password,
    ) {}

    // Метод toArray() преобразует DTO в обычный массив, что удобно для JSON-ответа.
    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'password' => $this->password,
        ];
    }
}