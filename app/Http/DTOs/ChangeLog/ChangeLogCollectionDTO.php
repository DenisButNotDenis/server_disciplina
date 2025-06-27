<?php

namespace App\Http\DTOs\ChangeLog;

use Spatie\LaravelData\DataCollection;

/**
 * Класс DTO для представления коллекции записей логов изменений.
 * (Пункт 10)
 */
final class ChangeLogCollectionDTO extends DataCollection
{
    public static function of(): string
    {
        return ChangeLogResourceDTO::class;
    }
}
