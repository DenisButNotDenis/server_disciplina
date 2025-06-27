<?php

namespace App\Http\DTOs\Role;

use Spatie\LaravelData\DataCollection;

final class RoleCollectionDTO extends DataCollection
{
    public static function of(): string
    {
        return RoleResourceDTO::class;
    }
}