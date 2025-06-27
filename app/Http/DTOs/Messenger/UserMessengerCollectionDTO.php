<?php

namespace App\Http\DTOs\Messenger;

use Spatie\LaravelData\DataCollection;

/**
 * DTO для представления коллекции связей пользователей с мессенджерами.
 * (Пункт 9)
 */
final class UserMessengerCollectionDTO extends DataCollection
{
    public static function of(): string
    {
        return UserMessengerResourceDTO::class;
    }
}
