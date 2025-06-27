<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo; // Для полиморфного отношения
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Для связи с пользователем

/**
 * Модель для таблицы 'change_logs'.
 * Отслеживает мутации сущностей.
 * (Пункт 8)
 */
class ChangeLog extends Model
{
    use HasFactory;

    /**
     * Имя таблицы, связанной с моделью.
     * @var string
     */
    protected $table = 'change_logs';

    /**
     * Атрибуты, которые могут быть массово присвоены.
     * @var array<int, string>
     */
    protected $fillable = [
        'mutatable_type',
        'mutatable_id',
        'old_values',
        'new_values',
        'user_id',
        'event',
    ];

    /**
     * Атрибуты, которые должны быть приведены к определенным типам.
     * (Важно для автоматического преобразования JSON-строк в массивы/объекты)
     * @var array<string, string>
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Определяет полиморфное отношение.
     * Позволяет узнать, какая модель была мутирована (User, Role, Permission и т.д.).
     * (Пункт 6.a.i - Ссылка на мутирующую сущность)
     */
    public function mutatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Определяет отношение "принадлежит к" для пользователя,
     * который совершил изменение.
     * (Пункт 6.a.ii - Ссылка на мутирующую запись (кто изменил))
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
