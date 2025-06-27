<?php

namespace App\Http\DTOs\UserRole;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

/**
 * Класс DTO для представления связи пользователя с ролью.
 */
final readonly class UserRoleResourceDTO extends Data
{
    public function __construct(
        public int $userId,
        public int $roleId,
        public Carbon $createdAt,
        public Carbon $updatedAt,
    ) {}

    public static function fromPivot(array $pivotData): self
    {
        return new self(
            userId: $pivotData['user_id'],
            roleId: $pivotData['role_id'],
            createdAt: Carbon::parse($pivotData['created_at']),
            updatedAt: Carbon::parse($pivotData['updated_at']),
        );
    }
}