<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Импортируем SoftDeletes
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // Для отношения многие ко многим

class Role extends Model
{
    use HasFactory, SoftDeletes; // Используем SoftDeletes

    /**
     * Имя таблицы, если оно отличается от имени модели во множественном числе.
     * @var string
     */
    protected $table = 'roles';

    /**
     * Атрибуты, которые могут быть массово присвоены.
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    /**
     * Атрибуты, которые должны быть приведены к определенным типам.
     * @var array<string, string>
     */
    protected $casts = [
        'deleted_at' => 'datetime', // Убедитесь, что deleted_at приводится к datetime
    ];

    /**
     * Метод для получения пользователей, которым назначена эта роль.
     * Отношение "многие ко многим" с моделью User через таблицу users_and_roles.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users_and_roles', 'role_id', 'user_id');
    }

    /**
     * Метод для получения разрешений, связанных с этой ролью.
     * Отношение "многие ко многим" с моделью Permission через таблицу roles_and_permissions.
     * (Пункт 20-21)
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'roles_and_permissions', 'role_id', 'permission_id')
                    ->wherePivot('deleted_at', null); // Учитываем мягкое удаление связей, если оно будет
    }
}
