<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Запускает сид для заполнения таблицы разрешений.
     * (Пункт 26, 15)
     */
    public function run(): void
    {
        $entities = ['user', 'role', 'permission', 'user_role', 'role_permission', 'changelog']; // Добавляем 'changelog'

        foreach ($entities as $entity) {
            $permissions = [
                "get-list-{$entity}" => "Получение списка {$entity}ов",
                "read-{$entity}" => "Чтение информации о {$entity}",
                "create-{$entity}" => "Создание {$entity}",
                "update-{$entity}" => "Обновление {$entity}",
                "delete-{$entity}" => "Удаление {$entity}",
                "restore-{$entity}" => "Восстановление {$entity}",
            ];

            // Новые разрешения для истории
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
        // Добавляем общее разрешение на просмотр всех логов
        Permission::firstOrCreate(
            ['code' => 'get-story-all'],
            [
                'name' => 'Получение всей истории изменений',
                'description' => 'Разрешение на просмотр всех записей в таблице change_logs.',
            ]
        );

        // Добавляем разрешение на откат изменений
        Permission::firstOrCreate(
            ['code' => 'revert-changelog'],
            [
                'name' => 'Откат записей логов изменений',
                'description' => 'Разрешение на возврат сущности к состоянию в записи лога.',
            ]
        );
    }
}
