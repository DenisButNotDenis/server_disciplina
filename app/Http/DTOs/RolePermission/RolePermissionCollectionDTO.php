<?php

namespace App\Http\DTOs\RolePermission;

use Spatie\LaravelData\DataCollection;

/**
 * Класс DTO для представления коллекции связей роли с разрешением.
 */
final class RolePermissionCollectionDTO extends DataCollection
{
    public static function of(): string
    {
        return RolePermissionResourceDTO::class;
    }
}
