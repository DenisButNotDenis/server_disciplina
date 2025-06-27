<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Запускает сиды приложения.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class, // Запускаем сид для ролей
            PermissionSeeder::class, // Запускаем сид для разрешений
            RolePermissionLinkSeeder::class, // Запускаем сид для связей ролей и разрешений
            // UserSeeder::class, // Если у вас есть сид для тестовых пользователей
        ]);
    }
}

