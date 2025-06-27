<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission; // Импортируем модель Permission

class PermissionSeeder extends Seeder
{
    /**
     * Запускает сид для заполнения таблицы разрешений.
     * (Пункт 26, а также новые разрешения для ЛР9, ЛР10)
     */
    public function run(): void
    {
        $entities = [
            'user',
            'role',
            'permission',
            'user_role',
            'role_permission',
            'changelog',
            'messenger',
            'user_messenger',
            'notification_log',
            'profile_picture', // Новая сущность для ЛР10
        ];

        foreach ($entities as $entity) {
            $permissions = [
                "get-list-{$entity}" => "Получение списка {$entity}ов",
                "read-{$entity}" => "Чтение информации о {$entity}",
                "create-{$entity}" => "Создание {$entity}",
                "update-{$entity}" => "Обновление {$entity}",
                "delete-{$entity}" => "Удаление {$entity}",
                "restore-{$entity}" => "Восстановление {$entity}",
            ];

            $permissions["get-story-{$entity}"] = "Получение истории изменений для {$entity}";

            foreach ($permissions as $code => $name) {
                Permission::firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'description' => "Разрешение на: {$name}.",
                    ]
                );
            }
        }

        Permission::firstOrCreate(
            ['code' => 'get-story-all'],
            [
                'name' => 'Получение всей истории изменений',
                'description' => 'Разрешение на просмотр всех записей в таблице change_logs.',
            ]
        );

        Permission::firstOrCreate(
            ['code' => 'revert-changelog'],
            [
                'name' => 'Откат записей логов изменений',
                'description' => 'Разрешение на возврат сущности к состоянию в записи лога.',
            ]
        );

        // Дополнительные специфические разрешения для ЛР9
        Permission::firstOrCreate(
            ['code' => 'manage-user-messenger-notifications'],
            [
                'name' => 'Управление уведомлениями пользователя через мессенджеры',
                'description' => 'Разрешение включать/отключать уведомления для связок UserMessenger.',
            ]
        );

        Permission::firstOrCreate(
            ['code' => 'get-notification-report'],
            [
                'name' => 'Получение отчета по логам уведомлений',
                'description' => 'Разрешение на получение сгенерированного отчета о логах уведомлений.',
            ]
        );

        // Новые специфические разрешения для ЛР10
        // (Пункт 7.a, 7.b, 15, 19)
        Permission::firstOrCreate(
            ['code' => 'upload-profile-picture'],
            [
                'name' => 'Загрузка фотографии профиля',
                'description' => 'Разрешение пользователю загружать фотографии к своему профилю.',
            ]
        );
        Permission::firstOrCreate(
            ['code' => 'delete-profile-picture'],
            [
                'name' => 'Удаление фотографии профиля',
                'description' => 'Разрешение пользователю удалять фотографию со своего профиля.',
            ]
        );
        Permission::firstOrCreate(
            ['code' => 'download-profile-picture'],
            [
                'name' => 'Скачивание своей фотографии профиля',
                'description' => 'Разрешение пользователю скачивать свою оригинальную фотографию профиля.',
            ]
        );
        Permission::firstOrCreate(
            ['code' => 'get-profile-pictures-archive'],
            [
                'name' => 'Выгрузка архива фотографий пользователей',
                'description' => 'Разрешение администратору выгружать архив со всеми актуальными фотографиями пользователей.',
            ]
        );
    }
}
