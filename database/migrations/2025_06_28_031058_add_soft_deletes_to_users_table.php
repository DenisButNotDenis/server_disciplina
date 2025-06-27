<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Запускает миграцию.
     * Добавляет столбец 'deleted_at' для мягкого удаления в таблицу 'users'.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Проверяем, существует ли столбец 'deleted_at', чтобы избежать ошибок при повторном запуске
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->after('updated_at'); // Добавляет столбец deleted_at
            }
        });
    }

    /**
     * Откатывает миграцию.
     * Удаляет столбец 'deleted_at' из таблицы 'users'.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes(); // Удаляет столбец deleted_at
            }
        });
    }
};