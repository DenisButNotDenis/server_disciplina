<?php

namespace App\Http\DTOs\LogRequest;

use Spatie\LaravelData\DataCollection;

final class LogRequestCollectionDTO extends DataCollection
{
    public static function of(): string
    {
        return LogRequestResourceDTO::class;
    }
}
