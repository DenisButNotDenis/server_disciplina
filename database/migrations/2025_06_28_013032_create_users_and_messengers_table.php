<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Запускает миграцию.
     * Создает таблицу 'users_and_messengers' для связывания пользователей с мессенджерами.
     * (Пункт 5.b, 7.b)
     */
    public function up(): void
    {
        Schema::create('users_and_messengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('Ссылка на пользователя'); // Пункт 7.b.i
            $table->foreignId('messenger_id')->constrained('messengers')->onDelete('cascade')->comment('Ссылка на мессенджер'); // Пункт 7.b.ii
            $table->string('messenger_user_id')->comment('Идентификатор/имя пользователя в мессенджере (например, Telegram Chat ID)'); // Пункт 7.b.iii
            $table->boolean('is_confirmed')->default(false)->comment('Статус подтверждения связки'); // Пункт 7.b.iv
            $table->timestamp('confirmed_at')->nullable()->comment('Дата подтверждения связки'); // Пункт 7.b.v
            $table->boolean('allow_notifications')->default(true)->comment('Флаг разрешения уведомления пользователя через мессенджер'); // Пункт 7.b.vi
            $table->timestamps();

            $table->unique(['user_id', 'messenger_id', 'messenger_user_id'], 'user_messenger_unique');
        });
    }

    /**
     * Откатывает миграцию.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_and_messengers');
    }
};
