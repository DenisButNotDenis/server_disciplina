<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Запускает миграцию.
     * Добавляет поле 'profile_picture_file_id' в таблицу 'users'.
     * (Пункт 6)
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('profile_picture_file_id') // Внешний ключ для связи с таблицей 'files'
                  ->nullable() // Пользователь может не иметь фото профиля
                  ->constrained('files') // Ссылка на таблицу 'files'
                  ->onDelete('set null') // Если файл удален, обнуляем ссылку в пользователе
                  ->after('birthday'); // Размещаем поле после birthday (можно выбрать другое место)
        });
    }

    /**
     * Откатывает миграцию.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_picture_file_id'); // Удаляем внешний ключ и само поле
        });
    }
};