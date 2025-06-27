<?php

namespace App\Http\DTOs\Role;

use Spatie\LaravelData\DataCollection;

/**
 * Класс DTO для представления коллекции ролей.
 * (Пункт 10)
 */
final class RoleCollectionDTO extends DataCollection
{
    // Этот класс автоматически будет содержать коллекцию RoleResourceDTO
    // благодаря тому, что он расширяет DataCollection.
    public static function of(): string
    {
        return RoleResourceDTO::class;
    }
}
