<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Profile Picture Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for user profile pictures, including
    | storage settings, validation rules, and avatar generation.
    |
    */

    'max_size_kb' => env('PROFILE_PICTURE_MAX_SIZE_KB', 2048), // Максимальный размер файла в КБ
    'allowed_mimes' => explode(',', env('PROFILE_PICTURE_ALLOWED_MIMES', 'jpeg,png,webp,gif')), // Разрешенные MIME-типы
    'min_width' => env('PROFILE_PICTURE_MIN_WIDTH', 200),     // Минимальная ширина изображения
    'min_height' => env('PROFILE_PICTURE_MIN_HEIGHT', 200),   // Минимальная высота изображения
    'aspect_ratio' => env('PROFILE_PICTURE_ASPECT_RATIO', '1/1'), // Ожидаемое соотношение сторон (ширина/высота)
    'storage_disk' => env('PROFILE_PICTURE_STORAGE_DISK', 'public'), // Диск хранения изображений (должен быть настроен в config/filesystems.php)
    'avatar_size' => env('PROFILE_PICTURE_AVATAR_SIZE', 128), // Размер аватара (например, 128px для 128x128)

    /*
    |--------------------------------------------------------------------------
    | Archive Export Configuration
    |--------------------------------------------------------------------------
    |
    | Settings related to exporting user photos as an archive.
    |
    */
    'archive_export_disk' => env('ARCHIVE_EXPORT_DISK', 'private'), // Диск для временного хранения архивов

];
