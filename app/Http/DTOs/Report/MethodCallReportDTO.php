<?php

namespace App\Http\DTOs\Report;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

/**
 * DTO для одной записи в рейтинге вызываемых методов.
 * (Пункт 5.a, 6)
 */
final readonly class MethodCallReportDTO extends Data
{
    public function __construct(
        public string $method,          // [Метод] (например, App\Http\Controllers\AuthController@login)
        public int $callCount,          // [Количество вызовов]
        public ?Carbon $lastCalledAt,   // Дата последней операции (Пункт 6)
    ) {}
}
