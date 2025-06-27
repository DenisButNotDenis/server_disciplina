<?php

namespace App\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\AccessToken; // Модель для токенов
use App\Models\UserRefreshToken; // ИСПРАВЛЕНО: Используем UserRefreshToken для рефреш-токенов
use Carbon\Carbon; // Добавляем Carbon, т.к. методы теперь принимают DateTimeInterface

trait HasApiTokens
{
    /**
     * Create a new access token for the user.
     *
     * @param array $abilities
     * @param \DateTimeInterface|null $expiresAt
     * @return \App\Models\AccessToken
     */
    public function createAccessToken(array $abilities = ['*'], \DateTimeInterface $expiresAt = null): AccessToken
    {
        $token = Str::random(60); // Генерируем случайную строку для токена

        return $this->accessTokens()->create([
            'token' => Hash::make($token), // Хэшируем токен для хранения
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ])->forceFill(['token' => $token]); // Временно возвращаем нехэшированный токен для пользователя
    }

    /**
     * Create a new refresh token for the user.
     *
     * @param \DateTimeInterface|null $expiresAt
     * @return \App\Models\UserRefreshToken // ИСПРАВЛЕНО: Тип возвращаемого значения
     */
    public function createRefreshToken(\DateTimeInterface $expiresAt = null): UserRefreshToken // ИСПРАВЛЕНО: Тип аргумента
    {
        $token = Str::random(60); // Генерируем случайную строку для токена

        return $this->refreshTokens()->create([
            'token' => Hash::make($token), // Хэшируем токен для хранения
            'expires_at' => $expiresAt,
        ])->forceFill(['token' => $token]);
    }

    /**
     * Get the access tokens for the user.
     */
    public function accessTokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccessToken::class);
    }

    /**
     * Get the refresh tokens for the user.
     */
    public function refreshTokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserRefreshToken::class); // ИСПРАВЛЕНО: Ссылаемся на UserRefreshToken
    }

    /**
     * Revoke all access tokens for the user.
     */
    public function revokeAllAccessTokens(): void
    {
        $this->accessTokens()->delete();
    }

    /**
     * Revoke all refresh tokens for the user.
     */
    public function revokeAllRefreshTokens(): void
    {
        $this->refreshTokens()->delete();
    }

    /**
     * Revoke a specific access token.
     *
     * @param string $plainTextToken The plain text token to revoke.
     * @return bool True if the token was found and deleted, false otherwise.
     * ИСПРАВЛЕНО: Возвращает bool, как ожидается в AuthController.
     */
    public function revokeAccessToken(string $plainTextToken): bool
    {
        $accessToken = $this->accessTokens()->get()->filter(function ($token) use ($plainTextToken) {
            return Hash::check($plainTextToken, $token->token);
        })->first();

        if ($accessToken) {
            $accessToken->delete();
            return true;
        }
        return false;
    }
}