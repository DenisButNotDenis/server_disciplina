<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Запускает миграцию.
     * Добавляет поля для двухфакторной аутентификации в таблицу 'users'.
     * (Пункт 2, 3, 7, 8, 18)
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Флаг, включена ли 2FA для пользователя
            $table->boolean('is_2fa_enabled')->default(false)->after('password');

            // Код 2FA, отправленный пользователю
            $table->string('two_factor_code')->nullable()->after('is_2fa_enabled');

            // Время истечения срока действия 2FA кода
            $table->timestamp('two_factor_code_expires_at')->nullable()->after('two_factor_code');

            // Информация о клиенте, запросившем код (для требования 11, 13)
            $table->string('two_factor_client_ip')->nullable()->after('two_factor_code_expires_at');
            $table->string('two_factor_user_agent')->nullable()->after('two_factor_client_ip');

            // Время последней неудачной попытки запроса/подтверждения кода (для требований 19, 20)
            $table->timestamp('two_factor_last_code_requested_at')->nullable()->after('two_factor_user_agent');
            $table->integer('two_factor_code_attempts')->default(0)->after('two_factor_last_code_requested_at');
        });
    }

    /**
     * Откатывает миграцию.
     * Удаляет поля 2FA из таблицы 'users'.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_2fa_enabled',
                'two_factor_code',
                'two_factor_code_expires_at',
                'two_factor_client_ip',
                'two_factor_user_agent',
                'two_factor_last_code_requested_at',
                'two_factor_code_attempts',
            ]);
        });
    }
};