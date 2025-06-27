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
     * (Пункт 28, а также новые разрешения для ЛР9)
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
        // Получаем все разрешения, включая новые из ЛР9
        $allPermissions = Permission::all()->pluck('id');
        // Прикрепляем все разрешения к роли "admin"
        $adminRole->permissions()->sync($allPermissions);

        // --- Пользовательские разрешения (обновите, если нужно) ---
        $userPermissions = [
            'get-list-user',     // Может получить список пользователей
            'read-user',         // Может читать информацию о пользователях
            'update-user',       // Может обновлять информацию о пользователях
            'read-role',         // Может читать информацию о ролях
            'read-permission',   // Может читать информацию о разрешениях
            'get-story-user',    // Пользователь может просматривать свою историю
            // Разрешения для мессенджеров (пользователь может управлять своими связками)
            'get-list-messenger', // Может видеть список доступных мессенджеров (с учетом среды)
            'read-messenger',     // Может читать информацию о мессенджерах
            'create-user_messenger', // Может прикреплять мессенджеры к себе
            'read-user_messenger',   // Может видеть свои прикрепленные мессенджеры
            'delete-user_messenger', // Может откреплять свои мессенджеры
            'update-user_messenger', // Может обновлять настройки своих прикрепленных мессенджеров (например, toggle_notifications)
        ];
        $userPermissionIds = Permission::whereIn('code', $userPermissions)->pluck('id');
        $userRole->permissions()->sync($userPermissionIds);

        // --- Гостевые разрешения (обновите, если нужно) ---
        $guestPermissions = [
            'get-list-user',
            'read-role',
            'read-permission',
            'get-list-messenger', // Гость тоже может видеть список мессенджеров
            'read-messenger',     // И читать их информацию
        ];
        $guestPermissionIds = Permission::whereIn('code', $guestPermissions)->pluck('id');
        $guestRole->permissions()->sync($guestPermissionIds);

        $this->command->info('Role-permission links configured successfully for LB9!');
    }
}
