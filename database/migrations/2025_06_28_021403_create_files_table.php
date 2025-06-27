<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Запускает миграцию.
     * Создает таблицу 'files' для хранения информации о загружаемых файлах.
     * (Пункт 5.a, 8.a)
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id(); // Служебные поля: i. Идентификатор файла

            $table->string('name')->comment('Наименование файла'); // 8.a.i. Наименование файла (оригинальное имя)
            $table->text('description')->nullable()->comment('Описание файла'); // 8.a.ii. Описание файла
            $table->string('format', 10)->comment('Формат файла (расширение)'); // 8.a.iii. Формат файла
            $table->unsignedBigInteger('size')->comment('Размер файла в байтах'); // 8.a.iv. Размер файла
            $table->string('path', 2048)->comment('Путь к файлу на сервере'); // 8.a.v. Ссылка к файлу на сервере (относительный путь в хранилище)
            // Примечание: Бинарные данные файла (8.a.vi) не рекомендуется хранить в БД для больших файлов.
            // Вместо этого мы будем хранить путь к файлу на диске.

            // 8.a.vii. Служебные поля
            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // deleted_at для мягкого удаления файлов
        });
    }

    /**
     * Откатывает миграцию.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};

