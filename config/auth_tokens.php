<?php

// config/auth_tokens.php
return [
    // Время жизни токена доступа в минутах
    'access_token_expiration_minutes' => env('ACCESS_TOKEN_EXPIRATION_MINUTES', 60),

    // Время жизни токена обновления в днях
    'refresh_token_expiration_days' => env('REFRESH_TOKEN_EXPIRATION_DAYS', 7),

    // Максимальное количество активных токенов доступа для одного пользователя.
    // Установите 0 для отсутствия лимита.
    'max_active_access_tokens' => env('MAX_ACTIVE_ACCESS_TOKENS', 5),
];