<?php

namespace App\Http\DTOs\Report;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

/**
 * DTO для одной записи в рейтинге пользователей.
 * (Пункт 5.c, 6)
 */
final readonly class UserActivityReportDTO extends Data
{
    public function __construct(
        public int $userId,                     // ID пользователя
        public string $username,                 // Имя пользователя
        public int $requestCount,               // Количество запросов
        public ?Carbon $lastRequestAt,          // Дата последнего запроса
        public int $modificationCount,          // Количество изменений
        public ?Carbon $lastModificationAt,     // Дата последнего изменения
        public int $permissionCount,            // Количество разрешений (для пользователя это количество разрешений, привязанных через роли)
        public ?Carbon $lastAuthorizationAt,    // Дата последней авторизации (т.е. последнего входа)
    ) {}
}
