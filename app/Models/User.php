?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\HasApiTokens; // Ваш кастомный трейт
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon; // Импортируем Carbon

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * Атрибуты, которые могут быть массово присвоены.
     * Добавляем новые поля 2FA.
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'birthday',
        'is_2fa_enabled', // Новое поле
        'two_factor_code', // Новое поле
        'two_factor_code_expires_at', // Новое поле
        'two_factor_client_ip', // Новое поле
        'two_factor_user_agent', // Новое поле
        'two_factor_last_code_requested_at', // Новое поле
        'two_factor_code_attempts', // Новое поле
    ];

    /**
     * Атрибуты, которые должны быть скрыты при сериализации.
     * Добавляем поля 2FA, которые не должны быть видны в ответах API.
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code', // Скрываем код
        'two_factor_code_expires_at', // Скрываем время истечения кода
        'two_factor_client_ip', // Скрываем IP
        'two_factor_user_agent', // Скрываем User Agent
        'two_factor_last_code_requested_at', // Скрываем
        'two_factor_code_attempts', // Скрываем
    ];

    /**
     * Атрибуты, которые должны быть приведены к определенным типам.
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

    // ... (Оставьте существующие методы из ЛР2/ЛР3: createAccessToken, createRefreshToken, revokeAccessToken, revokeAllAccessTokens, revokeAllRefreshTokens, accessTokens, refreshTokens)

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

    // --- Методы для 2FA ---

    /**
     * Проверяет, включена ли двухфакторная аутентификация для пользователя.
     * (Пункт 18)
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
     * (Пункт 6, 7, 9, 10, 11, 13)
     *
     * @param string $ip IP адрес клиента
     * @param string $userAgent User Agent клиента
     * @param int $expirationMinutes Время жизни кода в минутах (из .env)
     * @return string Сгенерированный код
     */
    public function generateTwoFactorCode(string $ip, string $userAgent, int $expirationMinutes): string
    {
        // Генерируем новый 6-значный код
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Обновляем поля пользователя
        $this->forceFill([ // Используем forceFill, чтобы обойти $fillable, если не все поля в нем
            'two_factor_code' => $code,
            'two_factor_code_expires_at' => Carbon::now()->addMinutes($expirationMinutes),
            'two_factor_client_ip' => $ip,
            'two_factor_user_agent' => $userAgent,
            'two_factor_last_code_requested_at' => Carbon::now(), // Обновляем время последнего запроса
            'two_factor_code_attempts' => 0, // Сбрасываем счетчик попыток
        ])->save();

        return $code;
    }

    /**
     * Отменяет текущий 2FA код.
     * (Пункт 9, 10)
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
     * (Пункт 19, 20)
     */
    public function incrementTwoFactorCodeAttempts(): void
    {
        $this->increment('two_factor_code_attempts');
    }

    /**
     * Сбрасывает счетчик попыток использования/запроса кода 2FA.
     * (Пункт 19, 20)
     */
    public function resetTwoFactorCodeAttempts(): void
    {
        $this->two_factor_code_attempts = 0;
        $this->save();
    }
}
