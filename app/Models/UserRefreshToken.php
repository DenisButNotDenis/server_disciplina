<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRefreshToken extends Model
{
    use HasFactory;

    protected $table = 'user_refresh_tokens'; // Указываем имя таблицы, если оно не соответствует имени модели в единственном числе

    protected $fillable = [
        'user_id',
        'token',
        'revoked',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked' => 'boolean', // Указываем, что revoked - это булево значение 
    ];

    /**
     * Связь с моделью User.
     * Один токен обновления принадлежит одному пользователю.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}