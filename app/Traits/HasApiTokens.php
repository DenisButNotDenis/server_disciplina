<?php

    namespace App\Traits; // <<< ЭТОТ NAMESPACE ОЧЕНЬ ВАЖЕН

    use Illuminate\Support\Str; // Если используете Str::random()
    use Illuminate\Support\Facades\Hash;
    use App\Models\AccessToken; // Модель для токенов
    use App\Models\RefreshToken; // Модель для рефреш-токенов

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
         * @return \App\Models\RefreshToken
         */
        public function createRefreshToken(\DateTimeInterface $expiresAt = null): RefreshToken
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
            return $this->hasMany(RefreshToken::class);
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
         */
        public function revokeAccessToken(string $tokenId): void
        {
            $this->accessTokens()->where('id', $tokenId)->delete();
        }
    }