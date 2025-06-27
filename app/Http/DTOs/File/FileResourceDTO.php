<?php

namespace App\Http\DTOs\File;

use Spatie\LaravelData\Data;
use Carbon\Carbon;
use App\Models\File;

/**
 * DTO для представления одного файла.
 * (Пункт 8.a.i-v, vii)
 */
final readonly class FileResourceDTO extends Data
{
    public function __construct(
        public int $id,
        public string $name, // Оригинальное имя файла
        public ?string $description,
        public string $format, // Расширение файла
        public int $size, // Размер в байтах
        public string $path, // Относительный путь к файлу в хранилище (например, 'users/1/profile_pictures/generated_name.jpg')
        public Carbon $createdAt,
        public Carbon $updatedAt,
        public ?Carbon $deletedAt = null,
        public string $url, // Добавляем полную URL для доступа (Пункт 8.a.v, 14)
        public string $avatarUrl, // Добавляем URL для аватара (Пункт 14)
    ) {}

    /**
     * Создает экземпляр DTO из объекта модели File.
     *
     * @param File $file Модель File.
     * @return self
     */
    public static function fromModel(File $file): self
    {
        // Базовый URL для файлов (будет взят из конфига/ENV)
        // Предполагается, что 'files' в config/filesystems.php настроен на 'public' диск
        // и доступен через /storage/
        $baseUrl = config('app.url') . '/storage/';
        $avatarPath = str_replace('.', '_avatar.', $file->path); // Формируем путь к аватару

        return new self(
            id: $file->id,
            name: $file->name,
            description: $file->description,
            format: $file->format,
            size: $file->size,
            path: $file->path,
            createdAt: $file->created_at,
            updatedAt: $file->updated_at,
            deletedAt: $file->deleted_at,
            url: $baseUrl . $file->path, // Полная URL к оригинальному файлу
            avatarUrl: $baseUrl . $avatarPath, // Полная URL к аватару
        );
    }
}