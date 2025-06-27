<?php

namespace App\Http\DTOs\Report;

use Spatie\LaravelData\DataCollection;

/**
 * DTO для представления коллекции записей логов уведомлений в отчете.
 * (Пункт 17)
 */
final class NotificationReportCollectionDTO extends DataCollection
{
    public static function of(): string
    {
        return NotificationReportResourceDTO::class;
    }
}
