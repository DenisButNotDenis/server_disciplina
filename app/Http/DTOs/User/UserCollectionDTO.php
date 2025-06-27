<?php

namespace App\Http\DTOs\User;

use Spatie\LaravelData\DataCollection;


final class UserCollectionDTO extends DataCollection
{
    // Указываем, что эта коллекция содержит DTO одного пользователя
    public static function of(): string
    {
        return UserResourceDTO::class;
    }
}
