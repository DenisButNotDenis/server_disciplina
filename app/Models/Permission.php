<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Импортируем SoftDeletes
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // Для отношения многие ко многим

class Permission extends Model
{
    use HasFactory, SoftDeletes; // Используем SoftDeletes

    /**
     * Имя таблицы, если оно отличается от имени модели во множественном числе.
     * @var string
     */
    protected $table = 'permissions';

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
     * Метод для получения ролей, которым принадлежит это разрешение.
     * Отношение "многие ко многим" с моделью Role через таблицу roles_and_permissions.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'roles_and_permissions', 'permission_id', 'role_id')
                    ->wherePivot('deleted_at', null); // Учитываем мягкое удаление связей, если оно будет
    }
}
