<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission; // Импортируем модель Permission

class PermissionSeeder extends Seeder
{
    /**
     * Запускает сид для заполнения таблицы разрешений.
     * (Пункт 26)
     */
    public function run(): void
    {
        $entities = ['user', 'role', 'permission', 'user_role', 'role_permission']; // user_role, role_permission для связующих таблиц

        foreach ($entities as $entity) {
            $permissions = [
                "get-list-{$entity}" => "Получение списка {$entity}ов",
                "read-{$entity}" => "Чтение информации о {$entity}",
                "create-{$entity}" => "Создание {$entity}",
                "update-{$entity}" => "Обновление {$entity}",
                "delete-{$entity}" => "Удаление {$entity}",
                "restore-{$entity}" => "Восстановление {$entity}",
            ];

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
    }
}
