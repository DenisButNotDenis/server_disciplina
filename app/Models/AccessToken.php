<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessToken extends Model
{
    use HasFactory;

    // Поля, которые можно массово заполнять
    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
    ];

    // Как преобразовывать типы данных полей
    protected $casts = [
        'expires_at' => 'datetime', // Указываем, что expires_at - это объект даты и времени
    ];

    /**
     * Связь с моделью User.
     * Один токен доступа принадлежит одному пользователю.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}