<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Запускает миграцию.
     * Создает таблицу 'permissions' для хранения информации о разрешениях.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id(); // Первичный ключ ID
            $table->string('name')->unique(); // Наименование разрешения (обязательное, уникальное)
            $table->string('code')->unique(); // Шифр разрешения (обязательный, уникальный)
            $table->text('description')->nullable(); // Описание разрешения (необязательное)
            $table->timestamps(); // Поля created_at и updated_at
            $table->softDeletes(); // Поле deleted_at для мягкого удаления
        });
    }

    /**
     * Откатывает миграцию.
     * Удаляет таблицу 'permissions'.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
