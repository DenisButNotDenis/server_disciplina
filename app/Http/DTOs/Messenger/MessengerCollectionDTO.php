<?php

namespace App\Http\DTOs\Messenger;

use Spatie\LaravelData\DataCollection;

/**
 * DTO для представления коллекции мессенджеров.
 * (Пункт 9)
 */
final class MessengerCollectionDTO extends DataCollection
{
    public static function of(): string
    {
        return MessengerResourceDTO::class;
    }
}
