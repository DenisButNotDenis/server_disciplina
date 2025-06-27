<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Для мягкого удаления файлов
use Illuminate\Database\Eloquent\Relations\HasOne; // Для обратной связи с User

/**
 * Модель для таблицы 'files'.
 * Хранит метаданные о загружаемых файлах (например, фотографиях пользователей).
 * (Пункт 5.a, 8.a)
 */
class File extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Имя таблицы, связанной с моделью.
     * @var string
     */
    protected $table = 'files';

    /**
     * Атрибуты, которые могут быть массово присвоены.
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'format',
        'size',
        'path', // Относительный путь к файлу в хранилище
    ];

    /**
     * Атрибуты, которые должны быть приведены к определенным типам.
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Получить пользователя, для которого этот файл является фотографией профиля.
     * Обратная связь "один к одному".
     */
    public function userProfilePicture(): HasOne
    {
        return $this->hasOne(User::class, 'profile_picture_file_id');
    }
}
