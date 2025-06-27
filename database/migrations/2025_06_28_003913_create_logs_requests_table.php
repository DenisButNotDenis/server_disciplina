<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Запускает миграцию.
     * Создает таблицу 'logs_requests' для логирования запросов пользователей и ответов.
     * (Пункт 5, 7)
     */
    public function up(): void
    {
        Schema::create('logs_requests', function (Blueprint $table) {
            $table->id(); // Первичный ключ

            // Информация о запросе
            $table->string('full_url', 2048)->comment('Полный адрес вызванного API метода'); // Пункт 7.a.i
            $table->string('http_method', 10)->comment('Метод HTTP запроса (GET, POST, etc.)'); // Пункт 7.a.ii
            $table->string('controller_path')->nullable()->comment('Путь до контроллера'); // Пункт 7.a.iii
            $table->string('controller_method')->nullable()->comment('Наименование метода контроллера'); // Пункт 7.a.iv
            $table->json('request_body')->nullable()->comment('Содержимое тела запроса'); // Пункт 7.a.v
            $table->json('request_headers')->nullable()->comment('Содержимое заголовков запроса'); // Пункт 7.a.vi

            // Информация о пользователе, если авторизован
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null')->comment('Идентификатор пользователя'); // Пункт 7.a.vii
            $table->string('ip_address')->nullable()->comment('IP адрес пользователя'); // Пункт 7.a.viii
            $table->string('user_agent', 1024)->nullable()->comment('User-Agent пользователя'); // Пункт 7.a.ix

            // Информация об ответе
            $table->unsignedSmallInteger('response_status')->comment('Код статуса ответа'); // Пункт 7.a.x
            $table->json('response_body')->nullable()->comment('Содержимое тела ответа'); // Пункт 7.a.xi
            $table->json('response_headers')->nullable()->comment('Заголовки тела ответа'); // Пункт 7.a.xii

            $table->timestamp('requested_at')->comment('Время вызова метода'); // Пункт 7.a.xiii
            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Откатывает миграцию.
     * Удаляет таблицу 'logs_requests'.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs_requests');
    }
};
