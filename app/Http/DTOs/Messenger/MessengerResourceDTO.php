<?php

namespace App\Http\DTOs\Messenger;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

/**
 * DTO для представления одного мессенджера.
 * (Пункт 9)
 */
final readonly class MessengerResourceDTO extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public string $environment,
        // public ?string $apiKeyEnvVar, // Не выводим чувствительные данные API-ключей
        public Carbon $createdAt,
        public Carbon $updatedAt,
    ) {}

    /**
     * Создает экземпляр DTO из объекта модели Messenger.
     *
     * @param \App\Models\Messenger $messenger Модель Messenger.
     * @return self
     */
    public static function fromModel(\App\Models\Messenger $messenger): self
    {
        return new self(
            id: $messenger->id,
            name: $messenger->name,
            description: $messenger->description,
            environment: $messenger->environment,
            createdAt: $messenger->created_at,
            updatedAt: $messenger->updated_at,
        );
    }
}
