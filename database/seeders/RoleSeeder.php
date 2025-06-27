<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role; // Импортируем модель Role

class RoleSeeder extends Seeder
{
    /**
     * Запускает сид для заполнения таблицы ролей.
     * (Пункт 25)
     */
    public function run(): void
    {
        // Создаем или обновляем роли
        Role::firstOrCreate(
            ['code' => 'admin'],
            [
                'name' => 'Администратор',
                'description' => 'Полный доступ ко всем функциям системы.',
            ]
        );

        Role::firstOrCreate(
            ['code' => 'user'],
            [
                'name' => 'Пользователь',
                'description' => 'Стандартный пользователь системы.',
            ]
        );

        Role::firstOrCreate(
            ['code' => 'guest'],
            [
                'name' => 'Гость',
                'description' => 'Пользователь без авторизации или с ограниченным доступом.',
            ]
        );
    }
}

