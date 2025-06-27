<?php

namespace App\Http\DTOs\User;

use Spatie\LaravelData\Data;
use Carbon\Carbon;
use App\Http\DTOs\Role\RoleCollectionDTO;
use App\Http\DTOs\File\FileResourceDTO; // Импортируем DTO для файла

/**
 * Класс DTO для представления одного пользователя.
 * (Пункт 14 - Добавить свойство со ссылкой на аватар пользователя)
 */
final readonly class UserResourceDTO extends Data
{
    public function __construct(
        public int $id,
        public string $username,
        public string $email,
        public ?Carbon $birthday,
        public Carbon $createdAt,
        public Carbon $updatedAt,
        public ?Carbon $deletedAt = null,
        public RoleCollectionDTO $roles,
        public bool $isTwoFactorEnabled,
        public ?string $profilePictureUrl = null, // URL к оригинальному изображению
        public ?string $profileAvatarUrl = null, // URL к аватару (128x128) (Пункт 14)
        public ?FileResourceDTO $profilePicture = null, // DTO самой фотографии, если нужно больше данных
    ) {}

    /**
     * Создает экземпляр DTO из объекта модели User.
     *
     * @param \App\Models\User $user Модель User.
     * @return self
     */
    public static function fromModel(\App\Models\User $user): self
    {
        if (!$user->relationLoaded('roles')) {
            $user->load('roles');
        }
        // Загружаем отношение profilePicture, если оно не было загружено
        if (!$user->relationLoaded('profilePicture')) {
            $user->load('profilePicture');
        }

        // Получаем URL'ы фотографий
        $profilePictureUrl = null;
        $profileAvatarUrl = null;
        $profilePictureDTO = null;

        if ($user->profilePicture) {
            // Используем FileResourceDTO для получения URL'ов и других данных файла
            $fileDTO = FileResourceDTO::fromModel($user->profilePicture);
            $profilePictureUrl = $fileDTO->url;
            $profileAvatarUrl = $fileDTO->avatarUrl;
            $profilePictureDTO = $fileDTO; // Передаем весь DTO файла
        }

        return new self(
            id: $user->id,
            username: $user->username,
            email: $user->email,
            birthday: $user->birthday,
            createdAt: $user->created_at,
            updatedAt: $user->updated_at,
            deletedAt: $user->deleted_at,
            roles: RoleCollectionDTO::collect($user->roles),
            isTwoFactorEnabled: $user->is_2fa_enabled,
            profilePictureUrl: $profilePictureUrl,
            profileAvatarUrl: $profileAvatarUrl,
            profilePicture: $profilePictureDTO,
        );
    }
}
