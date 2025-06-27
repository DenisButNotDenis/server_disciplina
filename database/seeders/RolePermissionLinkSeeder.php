<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role; // Импортируем модель Role
use App\Models\Permission; // Импортируем модель Permission

class RolePermissionLinkSeeder extends Seeder
{
    /**
     * Запускает сид для настройки связок ролей и разрешений.
     * (Пункт 28, а также новые разрешения для ЛР9, ЛР10)
     */
    public function run(): void
    {
        // Находим роли по их коду
        $adminRole = Role::where('code', 'admin')->first();
        $userRole = Role::where('code', 'user')->first();
        $guestRole = Role::where('code', 'guest')->first();

        // Если роли не найдены, возможно, RoleSeeder не был запущен.
        if (!$adminRole || !$userRole || !$guestRole) {
            $this->command->info('Roles not found. Please run RoleSeeder first.');
            return;
        }

        // --- Админ может все ---
        $allPermissions = Permission::all()->pluck('id');
        $adminRole->permissions()->sync($allPermissions);

        // --- Пользовательские разрешения ---
        $userPermissions = [
            'get-list-user',
            'read-user',
            'update-user',
            'read-role',
            'read-permission',
            'get-story-user',
            'get-list-messenger',
            'read-messenger',
            'create-user_messenger',
            'read-user_messenger',
            'delete-user_messenger',
            'update-user_messenger',
            // Разрешения для ЛР10: Пользователь может управлять СВОИМ фото профиля
            'upload-profile-picture',   // Загрузка фото профиля
            'delete-profile-picture',   // Удаление фото профиля
            'download-profile-picture', // Скачивание своего фото профиля
        ];
        $userPermissionIds = Permission::whereIn('code', $userPermissions)->pluck('id');
        $userRole->permissions()->sync($userPermissionIds);

        // --- Гостевые разрешения ---
        $guestPermissions = [
            'get-list-user',
            'read-role',
            'read-permission',
            'get-list-messenger',
            'read-messenger',
        ];
        $guestPermissionIds = Permission::whereIn('code', $guestPermissions)->pluck('id');
        $guestRole->permissions()->sync($guestPermissionIds);

        $this->command->info('Role-permission links configured successfully for LB10!');
    }
}