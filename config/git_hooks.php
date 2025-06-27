<?php

return [

    'secret' => env('GIT_WEBHOOK_SECRET'), // Секретный ключ для аутентификации webhook
    'branch_to_pull' => 'main',            // Ветка, которую нужно обновлять (обычно main)
    'update_lock_timeout' => 300,          // Время (в секундах) на которое устанавливается блокировка обновления
];
