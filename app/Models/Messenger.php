<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // Для связи с пользователями

/**
 * Модель для таблицы 'messengers'.
 * (Пункт 9)
 */
class Messenger extends Model
{
    use HasFactory;

    /**
     * Имя таблицы, связанной с моделью.
     * @var string
     */
    protected $table = 'messengers';

    /**
     * Атрибуты, которые могут быть массово присвоены.
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'environment',
        'api_key_env_var',
    ];

    /**
     * Атрибуты, которые должны быть приведены к определенным типам.
     * @var array<string, string>
     */
    protected $casts = [
        'environment' => 'string', // Может быть enum, но string для простоты
    ];

    /**
     * Связь "многие ко многим" с пользователями через таблицу users_and_messengers.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users_and_messengers', 'messenger_id', 'user_id')
                    ->withPivot('messenger_user_id', 'is_confirmed', 'confirmed_at', 'allow_notifications')
                    ->withTimestamps();
    }
}
