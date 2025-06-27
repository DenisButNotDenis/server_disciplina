<?php

namespace App\Http\DTOs\Report;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

/**
 * DTO для одной записи в рейтинге редактируемых сущностей.
 * (Пункт 5.b, 6)
 */
final readonly class EntityModificationReportDTO extends Data
{
    public function __construct(
        public string $entity,            // [Сущность] (например, App\Models\User, App\Models\Role)
        public int $modificationCount,    // [Количество изменений]
        public ?Carbon $lastModifiedAt,   // Дата последней операции (Пункт 6)
    ) {}
}
