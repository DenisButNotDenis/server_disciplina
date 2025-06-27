<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Запускает миграцию.
     * Создает таблицу 'notification_logs' для хранения логов уведомлений.
     * (Пункт 16)
     */
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null')->comment('Пользователь, которому предназначалось уведомление'); // Пункт 16.b
            $table->foreignId('messenger_id')->nullable()->constrained('messengers')->onDelete('set null')->comment('Мессенджер, через который отправлялось уведомление'); // Пункт 16.c
            $table->text('message_content')->comment('Содержимое отправленного сообщения'); // Пункт 16.d
            $table->enum('status', ['sent', 'failed', 'retrying'])->default('retrying')->comment('Статус отправки: sent, failed, retrying'); // Пункт 16.e
            $table->integer('attempt_number')->default(1)->comment('Номер попытки отправки'); // Пункт 16.f
            $table->text('error_message')->nullable()->comment('Сообщение об ошибке, если отправка не удалась');
            $table->timestamps(); // created_at (дата и время отправки), updated_at (обновления статуса) // Пункт 16.a
        });
    }

    /**
     * Откатывает миграцию.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
