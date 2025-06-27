<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission; // Импортируем модель Permission

class PermissionSeeder extends Seeder
{
    /**
     * Запускает сид для заполнения таблицы разрешений.
     * (Пункт 26, а также новые разрешения для ЛР9)
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
            'messenger',         // Новая сущность для ЛР9
            'user_messenger',    // Новая сущность для ЛР9
            'notification_log',  // Новая сущность для логов уведомлений
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

            // Новые разрешения для истории (из ЛР4)
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

        // Добавляем общее разрешение на просмотр всех логов (из ЛР4)
        Permission::firstOrCreate(
            ['code' => 'get-story-all'],
            [
                'name' => 'Получение всей истории изменений',
                'description' => 'Разрешение на просмотр всех записей в таблице change_logs.',
            ]
        );

        // Добавляем разрешение на откат изменений (из ЛР4)
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
                'description' => 'Разрешение на получение сгенерированного отчета о логах уведомлений.', // Пункт 18, 19
            ]
        );
    }
}