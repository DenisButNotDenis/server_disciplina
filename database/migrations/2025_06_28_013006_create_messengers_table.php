<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Запускает миграцию.
     * Создает таблицу 'messengers' для хранения информации о доступных мессенджерах.
     * (Пункт 5.a, 6.a, 7.a)
     */
    public function up(): void
    {
        Schema::create('messengers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Наименование мессенджера (например, Telegram, Viber)'); // Пункт 7.a.i
            $table->string('description')->nullable()->comment('Описание мессенджера'); // Пункт 7.a.ii
            $table->enum('environment', ['local', 'development', 'production'])->default('local')->comment('Среда окружения, для которой предназначен мессенджер'); // Пункт 6.a, 7.a.iii
            $table->string('api_key_env_var')->nullable()->comment('Наименование переменной окружения, содержащей токен/секретный ключ API'); // Пункт 7.a.iv
            $table->timestamps();
        });
    }

    /**
     * Откатывает миграцию.
     */
    public function down(): void
    {
        Schema::dropIfExists('messengers');
    }
};

