<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PermissionSeeder extends Seeder
{
    /**
     * Запускает заполнение базы данных разрешений.
     */
    public function run(): void
    {
        // Отключаем проверку внешних ключей временно для безопасного truncate и вставки
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('permissions')->truncate();
        DB::table('roles_and_permissions')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $permissions = [
            // Разрешения для управления ролями (ЛР3)
            ['name' => 'Просмотр списка ролей', 'code' => 'get-list-role', 'description' => 'Разрешает просмотр списка всех ролей.'],
            ['name' => 'Просмотр роли', 'code' => 'read-role', 'description' => 'Разрешает просмотр детальной информации о конкретной роли.'],
            ['name' => 'Создание роли', 'code' => 'create-role', 'description' => 'Разрешает создание новых ролей.'],
            ['name' => 'Изменение роли', 'code' => 'update-role', 'description' => 'Разрешает изменение существующих ролей.'],
            ['name' => 'Удаление роли', 'code' => 'delete-role', 'description' => 'Разрешает мягкое удаление ролей.'],
            ['name' => 'Восстановление роли', 'code' => 'restore-role', 'description' => 'Разрешает восстановление мягко удаленных ролей.'],

            // Разрешения для управления разрешениями (ЛР3)
            ['name' => 'Просмотр списка разрешений', 'code' => 'get-list-permission', 'description' => 'Разрешает просмотр списка всех разрешений.'],
            ['name' => 'Просмотр разрешения', 'code' => 'read-permission', 'description' => 'Разрешает просмотр детальной информации о конкретном разрешении.'],
            ['name' => 'Создание разрешения', 'code' => 'create-permission', 'description' => 'Разрешает создание новых разрешений.'],
            ['name' => 'Изменение разрешения', 'code' => 'update-permission', 'description' => 'Разрешает изменение существующих разрешений.'],
            ['name' => 'Удаление разрешения', 'code' => 'delete-permission', 'description' => 'Разрешает мягкое удаление разрешений.'],
            ['name' => 'Восстановление разрешения', 'code' => 'restore-permission', 'description' => 'Разрешает восстановление мягко удаленных разрешений.'],

            // Разрешения для управления пользователями (ЛР4)
            ['name' => 'Просмотр списка пользователей', 'code' => 'get-list-user', 'description' => 'Разрешает просмотр списка всех пользователей.'],
            ['name' => 'Просмотр пользователя', 'code' => 'read-user', 'description' => 'Разрешает просмотр детальной информации о конкретном пользователе.'],
            ['name' => 'Изменение пользователя', 'code' => 'update-user', 'description' => 'Разрешает изменение существующих пользователей.'],
            ['name' => 'Удаление пользователя', 'code' => 'delete-user', 'description' => 'Разрешает мягкое удаление пользователей.'],
            ['name' => 'Восстановление пользователя', 'code' => 'restore-user', 'description' => 'Разрешает восстановление мягко удаленных пользователей.'],

            // Разрешения для управления связями Пользователь-Роль (ЛР3)
            ['name' => 'Прикрепление роли к пользователю', 'code' => 'attach-user-role', 'description' => 'Разрешает прикрепление ролей к пользователям.'],
            ['name' => 'Открепление роли от пользователя', 'code' => 'detach-user-role', 'description' => 'Разрешает открепление ролей от пользователей.'],

            // Разрешения для управления связями Роль-Разрешение (ЛР3)
            ['name' => 'Прикрепление разрешения к роли', 'code' => 'attach-role-permission', 'description' => 'Разрешает прикрепление разрешений к ролям.'],
            ['name' => 'Открепление разрешения от роли', 'code' => 'detach-role-permission', 'description' => 'Разрешает открепление разрешений от ролей.'],

            // Разрешения для логов изменений (ЛР4)
            ['name' => 'Просмотр логов изменений', 'code' => 'view-change-logs', 'description' => 'Разрешает просмотр истории изменений моделей.'],
            ['name' => 'Откат изменений', 'code' => 'revert-change-logs', 'description' => 'Разрешает откат изменений модели по логу.'],

            // Новые разрешения для логов запросов (ЛР7)
            ['name' => 'Просмотр логов запросов', 'code' => 'view-request-logs', 'description' => 'Разрешает просмотр всех логов HTTP запросов и ответов.'], // Пункт 6.b
        ];

        foreach ($permissions as $data) {
            Permission::create($data);
        }

        // --- Назначение разрешений ролям ---
        /** @var \App\Models\Role $adminRole */
        $adminRole = Role::where('code', 'admin')->first();
        if ($adminRole) {
            // Получаем все созданные разрешения
            $allPermissions = Permission::all();
            $adminRole->permissions()->sync($allPermissions->pluck('id'));
        }

        /** @var \App\Models\Role $userRole */
        $userRole = Role::where('code', 'user')->first();
        if ($userRole) {
            // Пример: Пользователь может просматривать свои данные
            $userRole->permissions()->sync(Permission::whereIn('code', [
                'read-user',
            ])->pluck('id'));
        }
    }
}
