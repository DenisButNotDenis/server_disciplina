<?php

return [

    'data_interval_hours' => env('REPORT_INTERVAL_HOURS', 72), // Интервал данных для отчета в часах (Пункт 10)
    'max_execution_minutes' => env('REPORT_MAX_EXEC_MINUTES', 5), // Максимальный срок выполнения задачи в минутах (Пункт 11)

    'job_retries' => [
        'timeout_minutes' => env('REPORT_RETRY_TIMEOUT_MINUTES', 1), // Таймаут между повторениями в минутах (Пункт 12)
        'max_attempts' => env('REPORT_MAX_RETRIES', 3), // Количество повторений задачи (Пункт 13)
    ],

    'report_path' => storage_path('app/reports'), // Путь для временного хранения отчетов (будет создан)
    'report_format' => 'json', // Формат отчета 
];
