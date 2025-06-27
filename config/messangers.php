<?php

return [

    'environments' => [
        'local',
        'development',
        'production',
    ],

    'notification_retries' => [
        'max_attempts' => env('MESSENGER_NOTIFICATION_MAX_RETRIES', 3), // Пункт 15
        'retry_delay_seconds' => env('MESSENGER_NOTIFICATION_RETRY_DELAY', 60),
    ],

    // Конфигурации для конкретных мессенджеров
    'telegram' => [
        'token' => env('MESSENGER_TELEGRAM_TOKEN'),
        'base_url' => 'https://api.telegram.org/bot', // Базовый URL API Telegram
        'admin_chat_id' => env('MESSENGER_TELEGRAM_CHAT_ID'), // Chat ID для административных отчетов
    ],

];
