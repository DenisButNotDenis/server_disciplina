<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Запускает миграцию.
     * Создает таблицу 'roles' для хранения информации о ролях.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id(); // Первичный ключ ID
            $table->string('name')->unique(); // Наименование роли (обязательное, уникальное)
            $table->string('code')->unique(); // Шифр роли (обязательный, уникальный)
            $table->text('description')->nullable(); // Описание роли (необязательное)
            $table->timestamps(); // Поля created_at и updated_at
            $table->softDeletes(); // Поле deleted_at для мягкого удаления
        });
    }

    /**
     * Откатывает миграцию.
     * Удаляет таблицу 'roles'.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};