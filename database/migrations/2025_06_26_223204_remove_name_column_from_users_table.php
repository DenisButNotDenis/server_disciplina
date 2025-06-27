<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Проверяем, существует ли колонка 'name' перед удалением
            if (Schema::hasColumn('users', 'name')) {
                $table->dropColumn('name'); // Удаляем колонку 'name'
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // При откате миграции, добавляем колонку 'name' обратно.
            // Если колонка 'name' не должна быть nullable, нужно указать default() или after().
            // Для простоты, сделаем ее nullable при откате, чтобы не было проблем,
            // или вы можете добавить 'after('email')' и 'nullable()' в миграции создания users.
            // Лучше, если у вас в оригинальной миграции users ее просто не было.
            $table->string('name')->nullable(); // Добавляем обратно, если нужно
        });
    }
};