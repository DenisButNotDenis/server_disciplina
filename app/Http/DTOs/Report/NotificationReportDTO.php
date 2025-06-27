<?php

namespace App\Http\DTOs\Report;

use Spatie\LaravelData\Data;

/**
 * DTO для полного отчета по логам уведомлений.
 * (Пункт 17)
 */
final readonly class NotificationReportDTO extends Data
{
    public function __construct(
        public NotificationReportCollectionDTO $logs, // Коллекция логов уведомлений
        public int $totalLogs,                      // Общее количество записей
        public int $successfulSends,                // Количество успешных отправок
        public int $failedSends,                    // Количество неудачных отправок
        public int $skippedSends,                   // Количество пропущенных отправок
        public int $retryingSends,                  // Количество отправок в состоянии "повторная попытка"
    ) {}
}
