<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Для связи с пользователем

/**
 * Модель для таблицы 'logs_requests'.
 * Отслеживает логи запросов пользователей.
 * (Пункт 9)
 */
class LogRequest extends Model
{
    use HasFactory;

    /**
     * The table name associated with the model.
     * @var string
     */
    protected $table = 'logs_requests';

    /**
     * The attributes that are mass assignable.
     * @var array<int, string>
     */
    protected $fillable = [
        'full_url',
        'http_method',
        'controller_path',
        'controller_method',
        'request_body',
        'request_headers',
        'user_id',
        'ip_address',
        'user_agent',
        'response_status',
        'response_body',
        'response_headers',
        'requested_at',
    ];

    /**
     * The attributes that should be cast to native types.
     * (Important for automatic conversion of JSON strings to arrays/objects)
     * @var array<string, string>
     */
    protected $casts = [
        'request_body' => 'array',
        'request_headers' => 'array',
        'response_body' => 'array',
        'response_headers' => 'array',
        'requested_at' => 'datetime',
    ];

    /**
     * Define the "belongs to" relationship for the user who made the request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
