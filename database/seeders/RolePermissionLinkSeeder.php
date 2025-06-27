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
     * (Пункт 28)
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
        // Получаем все разрешения
        $allPermissions = Permission::all()->pluck('id');
        // Прикрепляем все разрешения к роли "admin"
        $adminRole->permissions()->sync($allPermissions); // sync() добавит/удалит, чтобы соответствовать списку

        // --- Пользователь может получить список пользователей, читать и обновлять свои данные ---
        $userPermissions = [
            'get-list-user',     // Может получить список пользователей
            'read-user',         // Может читать информацию о пользователях
            'update-user',       // Может обновлять информацию о пользователях
            'read-role',         // Может читать информацию о ролях
            'read-permission',   // Может читать информацию о разрешениях
        ];
        $userPermissionIds = Permission::whereIn('code', $userPermissions)->pluck('id');
        $userRole->permissions()->sync($userPermissionIds);

        // --- Гость может только получить список пользователей ---
        $guestPermissions = [
            'get-list-user',     // Может получить список пользователей
            'read-role',         // Может читать информацию о ролях (опционально, но полезно)
            'read-permission',   // Может читать информацию о разрешениях (опционально)
        ];
        $guestPermissionIds = Permission::whereIn('code', $guestPermissions)->pluck('id');
        $guestRole->permissions()->sync($guestPermissionIds);

        $this->command->info('Role-permission links configured successfully!');
    }
}
