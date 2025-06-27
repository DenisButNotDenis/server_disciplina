<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\HasApiTokens; // Ваш кастомный трейт
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon; // Импортируем Carbon для работы с датами

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes; // Важно: убедитесь, что все трейты здесь

    /**
     * The attributes that are mass assignable.
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'birthday',
        'is_2fa_enabled', // Новое поле для 2FA
        'two_factor_code', // Новое поле для 2FA
        'two_factor_code_expires_at', // Новое поле для 2FA
        'two_factor_client_ip', // Новое поле для 2FA
        'two_factor_user_agent', // Новое поле для 2FA
        'two_factor_last_code_requested_at', // Новое поле для 2FA
        'two_factor_code_attempts', // Новое поле для 2FA
    ];

    /**
     * The attributes that should be hidden for serialization.
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code', // Скрываем код 2FA
        'two_factor_code_expires_at', // Скрываем время истечения кода 2FA
        'two_factor_client_ip', // Скрываем IP клиента для 2FA
        'two_factor_user_agent', // Скрываем User Agent для 2FA
        'two_factor_last_code_requested_at', // Скрываем время последнего запроса 2FA
        'two_factor_code_attempts', // Скрываем счетчик попыток 2FA
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
        'is_2fa_enabled' => 'boolean', // Приводим к булевому типу
        'two_factor_code_expires_at' => 'datetime', // Приводим к Carbon
        'two_factor_last_code_requested_at' => 'datetime', // Приводим к Carbon
    ];

    // Методы для работы с токенами (из HasApiTokens трейта)
    // Внимание: Эти методы реализованы в трейте HasApiTokens,
    // если они здесь дублируются, удалите дубликаты.
    // Если трейт HasApiTokens не используется, тогда оставьте их здесь.
    // Если у вас версия HasApiTokens, где эти методы уже есть, то просто убедитесь,
    // что use HasApiTokens, и никаких дубликатов здесь нет, иначе будут конфликты.
    // Я предполагаю, что вы используете трейт, поэтому оставляю только связи и методы 2FA.

    /**
     * Get the access tokens for the user.
     */
    public function accessTokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccessToken::class);
    }

    /**
     * Get the refresh tokens for the user.
     */
    public function refreshTokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserRefreshToken::class); // Используем UserRefreshToken, как в вашем коде
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

    /**
     * Проверяет, включена ли двухфакторная аутентификация для пользователя.
     */
    public function twoFactorAuthActive(): bool
    {
        return (bool) $this->is_2fa_enabled;
    }

    /**
     * Проверяет, есть ли активный 2FA код и не истек ли его срок действия.
     */
    public function hasActiveTwoFactorCode(): bool
    {
        return !empty($this->two_factor_code) &&
               $this->two_factor_code_expires_at &&
               $this->two_factor_code_expires_at->isFuture();
    }

    /**
     * Проверяет, соответствует ли предоставленный код активному 2FA коду.
     *
     * @param string $code
     * @return bool
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        return $this->two_factor_code === $code && $this->hasActiveTwoFactorCode();
    }

    /**
     * Генерирует и сохраняет новый 2FA код для пользователя.
     *
     * @param string $ip IP адрес клиента
     * @param string $userAgent User Agent клиента
     * @param int $expirationMinutes Время жизни кода в минутах (из .env)
     * @return string Сгенерированный код
     */
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

    /**
     * Отменяет текущий 2FA код.
     */
    public function invalidateTwoFactorCode(): void
    {
        $this->forceFill([
            'two_factor_code' => null,
            'two_factor_code_expires_at' => null,
            'two_factor_client_ip' => null,
            'two_factor_user_agent' => null,
        ])->save();
    }

    /**
     * Увеличивает счетчик попыток использования/запроса кода 2FA.
     */
    public function incrementTwoFactorCodeAttempts(): void
    {
        $this->increment('two_factor_code_attempts');
    }

    /**
     * Сбрасывает счетчик попыток использования/запроса кода 2FA.
     */
    public function resetTwoFactorCodeAttempts(): void
    {
        $this->two_factor_code_attempts = 0;
        $this->save();
    }
}
