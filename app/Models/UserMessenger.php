<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель для таблицы 'users_and_messengers'.
 * (Пункт 9)
 */
class UserMessenger extends Model
{
    use HasFactory;

    /**
     * Имя таблицы, связанной с моделью.
     * @var string
     */
    protected $table = 'users_and_messengers';

    /**
     * Атрибуты, которые могут быть массово присвоены.
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'messenger_id',
        'messenger_user_id',
        'is_confirmed',
        'confirmed_at',
        'allow_notifications',
    ];

    /**
     * Атрибуты, которые должны быть приведены к определенным типам.
     * @var array<string, string>
     */
    protected $casts = [
        'is_confirmed' => 'boolean',
        'confirmed_at' => 'datetime',
        'allow_notifications' => 'boolean',
    ];

    /**
     * Связь с моделью User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Связь с моделью Messenger.
     */
    public function messenger(): BelongsTo
    {
        return $this->belongsTo(Messenger::class);
    }
}
