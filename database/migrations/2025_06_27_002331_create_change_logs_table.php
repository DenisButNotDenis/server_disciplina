<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Запускает миграцию.
     * Создает таблицу 'change_logs' для логирования мутаций сущностей.
     * (Пункт 4, 6)
     */
    public function up(): void
    {
        Schema::create('change_logs', function (Blueprint $table) {
            $table->id(); // Первичный ключ
            $table->morphs('mutatable'); // Полиморфное отношение: ссылка на мутирующую сущность (type и id)
                                         // 'mutatable_type' (строка) - полное имя класса модели (например, 'App\Models\User')
                                         // 'mutatable_id' (целое число) - ID записи модели

            $table->json('old_values')->nullable(); // Значение записи до мутации (в формате JSON)
            $table->json('new_values')->nullable(); // Значение записи после мутации (в формате JSON)

            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
                                         // ID пользователя, который совершил мутацию. Nullable, если мутация может быть системной.
            $table->string('event')->comment('Тип события: created, updated, deleted, restored');
                                         // Тип события

            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Откатывает миграцию.
     * Удаляет таблицу 'change_logs'.
     */
    public function down(): void
    {
        Schema::dropIfExists('change_logs');
    }
};
