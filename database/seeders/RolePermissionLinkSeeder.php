<?php

<<<<<<< HEAD
namespace Database\Seeders;
=======
namespace Database\Seeder;
>>>>>>> lb3

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionLinkSeeder extends Seeder
{
    /**
     * Запускает сид для настройки связок ролей и разрешений.
     * (Пункт 28, 16)
     */
    public function run(): void
    {
        $adminRole = Role::where('code', 'admin')->first();
        $userRole = Role::where('code', 'user')->first();
        $guestRole = Role::where('code', 'guest')->first();

        if (!$adminRole || !$userRole || !$guestRole) {
            $this->command->info('Roles not found. Please run RoleSeeder first.');
            return;
        }

        // --- Админ может все ---
        // Получаем все разрешения, включая новые 'get-story-*' и 'revert-changelog'
        $allPermissions = Permission::all()->pluck('id');
        $adminRole->permissions()->sync($allPermissions);

        // --- Пользователь может получить список пользователей, читать и обновлять свои данные ---
        // И добавим возможность просмотра своей истории
        $userPermissions = [
            'get-list-user',
            'read-user',
            'update-user',
            'read-role',
            'read-permission',
            'get-story-user', // Пользователь может просматривать свою историю
        ];
        $userPermissionIds = Permission::whereIn('code', $userPermissions)->pluck('id');
        $userRole->permissions()->sync($userPermissionIds);

        // --- Гость может только получить список пользователей ---
        $guestPermissions = [
            'get-list-user',
            'read-role',
            'read-permission',
        ];
        $guestPermissionIds = Permission::whereIn('code', $guestPermissions)->pluck('id');
        $guestRole->permissions()->sync($guestPermissionIds);

        $this->command->info('Role-permission links configured successfully!');
    }
}
