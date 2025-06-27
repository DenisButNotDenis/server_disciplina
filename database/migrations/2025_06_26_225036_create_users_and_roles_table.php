<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Запускает миграцию.
     * Создает связующую таблицу 'users_and_roles' для отношения "многие ко многим" между пользователями и ролями.
     */
    public function up(): void
    {
        Schema::create('users_and_roles', function (Blueprint $table) {
            $table->id(); // Первичный ключ для связующей таблицы
            $table->foreignId('user_id') // Внешний ключ для связи с таблицей 'users'
                  ->constrained('users') // Указываем, что это ссылка на таблицу 'users'
                  ->onDelete('cascade'); // При удалении пользователя, удалять все его связи с ролями
            $table->foreignId('role_id') // Внешний ключ для связи с таблицей 'roles'
                  ->constrained('roles') // Указываем, что это ссылка на таблицу 'roles'
                  ->onDelete('cascade'); // При удалении роли, удалять все связанные записи

            $table->unique(['user_id', 'role_id']); // Композитный уникальный индекс, чтобы пользователь не мог иметь одну и ту же роль несколько раз
            $table->timestamps(); // Служебные поля
        });
    }

    /**
     * Откатывает миграцию.
     * Удаляет таблицу 'users_and_roles'.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_and_roles');
    }
};
