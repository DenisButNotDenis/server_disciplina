?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель для таблицы 'notification_logs'.
 * (Пункт 16)
 */
class NotificationLog extends Model
{
    use HasFactory;

    protected $table = 'notification_logs';

    protected $fillable = [
        'user_id',
        'messenger_id',
        'message_content',
        'status',
        'attempt_number',
        'error_message',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Связь с пользователем, которому предназначалось уведомление.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Связь с мессенджером, через который отправлялось уведомление.
     */
    public function messenger(): BelongsTo
    {
        return $this->belongsTo(Messenger::class);
    }
}
