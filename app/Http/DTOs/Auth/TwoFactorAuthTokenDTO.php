<?php

namespace App\Http\DTOs\Auth;

use Spatie\LaravelData\Data;

/**
 * DTO для выдачи временного токена подтверждения 2FA.
 * (Пункт 14)
 */
final readonly class TwoFactorAuthTokenDTO extends Data
{
    public function __construct(
        public string $twoFactorToken,       // Специальный токен для 2FA-операций
        public string $message = 'Two-factor authentication required. Please verify your identity.', // Сообщение
    ) {}
}
