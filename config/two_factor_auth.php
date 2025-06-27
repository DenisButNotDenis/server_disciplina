<?php

return [

    'code_expiration_minutes' => env('TWO_FACTOR_CODE_EXPIRATION_MINUTES', 5), // Время жизни кода в минутах

    'rate_limits' => [
        // Лимит для конкретного клиента/IP
        'client' => [
            'threshold' => env('TWO_FACTOR_CLIENT_RATE_LIMIT_THRESHOLD', 3), // Количество запросов подряд с одного клиента
            'delay_seconds' => env('TWO_FACTOR_CLIENT_RATE_LIMIT_DELAY_SECONDS', 30), // Задержка в секундах
        ],
        // Глобальный лимит (без учета клиента)
        'global' => [
            'threshold' => env('TWO_FACTOR_GLOBAL_RATE_LIMIT_THRESHOLD', 5), // Общее количество запросов подряд
            'delay_seconds' => env('TWO_FACTOR_GLOBAL_RATE_LIMIT_DELAY_SECONDS', 50), // Задержка в секундах
        ],
    ],
];
