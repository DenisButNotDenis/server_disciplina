<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Запускает миграцию.
     * Создает связующую таблицу 'roles_and_permissions' для отношения "многие ко многим" между ролями и разрешениями.
     */
    public function up(): void
    {
        Schema::create('roles_and_permissions', function (Blueprint $table) {
            $table->id(); // Первичный ключ для связующей таблицы
            $table->foreignId('role_id') // Внешний ключ для связи с таблицей 'roles'
                  ->constrained('roles') // Указываем, что это ссылка на таблицу 'roles'
                  ->onDelete('cascade'); // При удалении роли, удалять все ее связи с разрешениями
            $table->foreignId('permission_id') // Внешний ключ для связи с таблицей 'permissions'
                  ->constrained('permissions') // Указываем, что это ссылка на таблицу 'permissions'
                  ->onDelete('cascade'); // При удалении разрешения, удалять все связанные записи

            $table->unique(['role_id', 'permission_id']); // Композитный уникальный индекс, чтобы роль не могла иметь одно и то же разрешение несколько раз
            $table->timestamps(); // Служебные поля
        });
    }

    /**
     * Откатывает миграцию.
     * Удаляет таблицу 'roles_and_permissions'.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles_and_permissions');
    }
};
