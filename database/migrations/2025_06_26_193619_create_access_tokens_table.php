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
        Schema::create('access_tokens', function (Blueprint $table) {
            $table->id(); // Автоматический ID для каждой записи
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // ID пользователя, к которому относится токен. onDelete('cascade') значит, что если пользователь удалится, его токены тоже удалятся.
            $table->string('token', 64)->unique(); // Здесь будет храниться ЗАХЭШИРОВАННЫЙ токен (длиной 64 символа), он должен быть уникальным
            $table->timestamp('expires_at')->nullable(); // Дата и время, когда токен истекает. nullable() значит, что поле может быть пустым.
            $table->timestamps(); // Добавляет поля `created_at` и `updated_at` (дата создания и последнего обновления записи)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_tokens');
    }
};
