<?php

namespace App\Http\DTOs\Role;

use Spatie\LaravelData\Data;
use Carbon\Carbon; // Используем Carbon для полей даты

/**
 * Класс DTO для представления одной роли.
 * (Пункт 9)
 */
final readonly class RoleResourceDTO extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $code,
        public ?string $description, // Описание может быть nullable
        public Carbon $createdAt,
        public Carbon $updatedAt,
        public ?Carbon $deletedAt = null, // Для мягкого удаления
    ) {}

    /**
     * Создает экземпляр DTO из объекта модели Role.
     *
     * @param \App\Models\Role $role Модель Role.
     * @return self
     */
    public static function fromModel(\App\Models\Role $role): self
    {
        return new self(
            id: $role->id,
            name: $role->name,
            code: $role->code,
            description: $role->description,
            createdAt: $role->created_at,
            updatedAt: $role->updated_at,
            deletedAt: $role->deleted_at,
        );
    }
}
