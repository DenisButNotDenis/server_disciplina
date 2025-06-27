<?php

namespace App\Http\DTOs\Auth;

class TokenResourceDTO
{
    public function __construct(
        public string $accessToken,
        public string $tokenType = 'Bearer', // Стандартный тип токена
        public ?string $refreshToken = null, // Может быть null
        public ?int $expiresInSeconds = null, // Время жизни Access Token в секундах
    ) {}

    public function toArray(): array
    {
        $data = [
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
        ];

        // Если refreshToken не null, добавляем его в массив
        if ($this->refreshToken !== null) {
            $data['refresh_token'] = $this->refreshToken;
        }

        // Если expiresInSeconds не null, добавляем его
        if ($this->expiresInSeconds !== null) {
            $data['expires_in'] = $this->expiresInSeconds;
        }

        return $data;
    }
}