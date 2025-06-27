<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Добавлено для связи с File
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use App\Jobs\SendMessengerNotificationJob;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     * Добавляем 'profile_picture_file_id'.
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'birthday',
        'is_2fa_enabled',
        'two_factor_code',
        'two_factor_code_expires_at',
        'two_factor_client_ip',
        'two_factor_user_agent',
        'two_factor_last_code_requested_at',
        'two_factor_code_attempts',
        'profile_picture_file_id', // Новое поле для ID фотографии профиля (Пункт 6)
    ];

    /**
     * The attributes that should be hidden for serialization.
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code',
        'two_factor_code_expires_at',
        'two_factor_client_ip',
        'two_factor_user_agent',
        'two_factor_last_code_requested_at',
        'two_factor_code_attempts',
    ];

    /**
     * The attributes that should be cast.
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'birthday' => 'date',
        'deleted_at' => 'datetime',
        'is_2fa_enabled' => 'boolean',
        'two_factor_code_expires_at' => 'datetime',
        'two_factor_last_code_requested_at' => 'datetime',
    ];

    /**
     * Get the access tokens for the user.
     */
    public function accessTokens(): HasMany
    {
        return $this->hasMany(AccessToken::class);
    }

    /**
     * Get the refresh tokens for the user.
     */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(UserRefreshToken::class);
    }

    /**
     * Метод для получения ролей, которыми обладает пользователь.
     * Отношение "многие ко многим" с моделью Role через таблицу `users_and_roles`.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'users_and_roles', 'user_id', 'role_id');
    }

    /**
     * Метод для получения связей с мессенджерами для пользователя.
     * Отношение "один ко многим" с моделью UserMessenger.
     */
    public function messengersRelation(): HasMany
    {
        return $this->hasMany(UserMessenger::class, 'user_id');
    }

    /**
     * Получить фотографию профиля пользователя.
     * (Пункт 6 - Ссылка на фотографию)
     */
    public function profilePicture(): BelongsTo
    {
        return $this->belongsTo(File::class, 'profile_picture_file_id');
    }

    /**
     * Проверяет, обладает ли пользователь определенной ролью по коду роли.
     */
    public function hasRole(string $roleCode): bool
    {
        return $this->roles->pluck('code')->contains($roleCode);
    }

    /**
     * Проверяет, обладает ли пользователь определенным разрешением по коду разрешения.
     */
    public function hasPermission(string $permissionCode): bool
    {
        return $this->roles->flatMap(fn ($role) => $role->permissions)
                           ->pluck('code')
                           ->contains($permissionCode);
    }

    // --- Методы для 2FA (из ЛР5) ---
    public function twoFactorAuthActive(): bool { return (bool) $this->is_2fa_enabled; }
    public function hasActiveTwoFactorCode(): bool { return !empty($this->two_factor_code) && $this->two_factor_code_expires_at && $this->two_factor_code_expires_at->isFuture(); }
    public function verifyTwoFactorCode(string $code): bool { return $this->two_factor_code === $code && $this->hasActiveTwoFactorCode(); }
    public function generateTwoFactorCode(string $ip, string $userAgent, int $expirationMinutes): string
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->forceFill([
            'two_factor_code' => $code,
            'two_factor_code_expires_at' => Carbon::now()->addMinutes($expirationMinutes),
            'two_factor_client_ip' => $ip,
            'two_factor_user_agent' => $userAgent,
            'two_factor_last_code_requested_at' => Carbon::now(),
            'two_factor_code_attempts' => 0,
        ])->save();
        return $code;
    }
    public function invalidateTwoFactorCode(): void
    {
        $this->forceFill([
            'two_factor_code' => null,
            'two_factor_code_expires_at' => null,
            'two_factor_client_ip' => null,
            'two_factor_user_agent' => null,
        ])->save();
    }
    public function incrementTwoFactorCodeAttempts(): void { $this->increment('two_factor_code_attempts'); }
    public function resetTwoFactorCodeAttempts(): void { $this->two_factor_code_attempts = 0; $this->save(); }

    /**
     * Отправляет уведомление пользователю через все подтвержденные и разрешенные мессенджеры.
     * (Пункт 12 ЛР9)
     *
     * @param string $message Сообщение для отправки.
     * @param string $eventName Имя события (например, 'user_registered', 'password_changed', 'role_assigned').
     */
    public function sendMessengerNotification(string $message, string $eventName): void
    {
        $confirmedUserMessengers = $this->messengersRelation()
                                        ->where('is_confirmed', true)
                                        ->where('allow_notifications', true)
                                        ->with('messenger')
                                        ->get();

        if ($confirmedUserMessengers->isEmpty()) {
            \Log::info("No confirmed messengers found for user {$this->id} for event '{$eventName}'. Notification skipped.");
            return;
        }

        foreach ($confirmedUserMessengers as $userMessenger) {
            SendMessengerNotificationJob::dispatch(
                $this->id,
                $userMessenger->messenger_id,
                $userMessenger->id,
                "Событие: {$eventName}\n" . $message
            )->onQueue('notifications');
            \Log::info("Notification Job dispatched for user {$this->id} via {$userMessenger->messenger->name} for event '{$eventName}'.");
        }
    }
}
