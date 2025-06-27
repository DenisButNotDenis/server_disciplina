?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
<<<<<<< HEAD
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
=======
use App\Traits\HasApiTokens; 

use Illuminate\Database\Eloquent\Relations\BelongsToMany; // Для отношения "многие ко многим" с ролями
use Illuminate\Database\Eloquent\SoftDeletes; // Для мягкого удаления пользователей

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens; 
    use SoftDeletes; 
    /**
     * Атрибуты, которые могут быть массово присвоены.
     * @var array<int, string>
     */
    protected $fillable = [
        'username', 
        'email',
        'password',
        'birthday', 
>>>>>>> lb3
    ];

    /**
     * Атрибуты, которые должны быть скрыты при сериализации.
<<<<<<< HEAD
     * Добавляем поля 2FA, которые не должны быть видны в ответах API.
=======
>>>>>>> lb3
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
<<<<<<< HEAD
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
=======
        'password' => 'hashed', // автоматически хэширует пароль при присваивании, если 'hashed'
        'birthday' => 'date',   // Важно: 'date', чтобы Laravel конвертировал в Carbon
        'deleted_at' => 'datetime', // Добавляем, если используете SoftDeletes
    ];

    public function createAccessToken(int $expirationMinutes = 60): string
    {
        $plainTextToken = Str::random(60); // Генерируем случайную строку длиной 60 символов
        $hashedToken = hash('sha256', $plainTextToken); // Хэшируем её с помощью SHA256 (необратимое преобразование)

        $this->accessTokens()->create([
            'token' => $hashedToken, // В базу данных сохраняем только хэш токена
            'expires_at' => Carbon::now()->addMinutes($expirationMinutes), // Устанавливаем срок годности
        ]);

        return $plainTextToken; // Возвращаем клиенту НЕЗАХЭШИРОВАННЫЙ токен
    }

    /**
     * Отзывает (удаляет) конкретный токен доступа пользователя.
     *
     * @param string $plainTextToken Открытый токен, который нужно отозвать.
     * @return bool Успешно ли отозван токен.
     */
    public function revokeAccessToken(string $plainTextToken): bool
    {
        $hashedToken = hash('sha256', $plainTextToken); // Снова хэшируем, чтобы найти в БД
        // Ищем токен по хэшу и удаляем его
        return $this->accessTokens()->where('token', $hashedToken)->delete() > 0;
    }

    /**
     * Отзывает все токены доступа пользователя.
     *
     * @return int Количество отозванных токенов.
     */
    public function revokeAllAccessTokens(): int
    {
        return $this->accessTokens()->delete(); // Удаляем все записи о токенах доступа для этого пользователя
    }

    // --- Методы для работы с токенами обновления ---

    /**
     * Связь модели User с моделью UserRefreshToken.
     * Один пользователь может иметь много токенов обновления.
     */
    public function refreshTokens()
    {
        return $this->hasMany(UserRefreshToken::class);
    }

    /**
     * Генерирует новый токен обновления для пользователя.
     *
     * @param int $expirationDays Время, через сколько дней токен "умрет".
     * @return string Сам токен обновления (незахэшированный), который мы выдадим клиенту.
     */
    public function createRefreshToken(int $expirationDays = 7): string
    {
        $plainTextToken = Str::random(64); // Токен обновления обычно длиннее
        $hashedToken = Hash::make($plainTextToken); // Хэшируем его с помощью алгоритма BCrypt (как пароли)

        $this->refreshTokens()->create([
            'token' => $hashedToken, // Сохраняем хэш
            'expires_at' => Carbon::now()->addDays($expirationDays), // Срок действия
            'revoked' => false, // Изначально токен не отозван
        ]);

        return $plainTextToken;
    }

    /**
     * Проверяет, является ли переданный токен обновления валидным и не отозванным.
     *
     * @param string $plainTextToken Открытый токен обновления.
     * @return \App\Models\UserRefreshToken|null Объект токена обновления, если найден и валиден, иначе null.
     */
    public function isValidRefreshToken(string $plainTextToken): ?UserRefreshToken
    {
        // Ищем все не отозванные и не просроченные токены обновления для этого пользователя
        return $this->refreshTokens()
            ->where('revoked', false)
            ->where('expires_at', '>', Carbon::now()) // Токен не должен быть просрочен
            ->get() // Получаем все такие токены
            ->filter(function ($refreshToken) use ($plainTextToken) {
                // Для каждого токена проверяем, соответствует ли его хэш переданному токену
                return Hash::check($plainTextToken, $refreshToken->token);
            })->first(); // Возвращаем первый найденный (подразумеваем, что refresh-токен уникален)
    }

    /**
     * Отзывает все токены обновления пользователя (помечает их как revoked).
     *
     * @return int Количество отозванных токенов.
     */
    public function revokeAllRefreshTokens(): int
    {
        return $this->refreshTokens()->update(['revoked' => true]);
    }

    /**
     * Метод для получения ролей, которыми обладает пользователь.
     * Отношение "многие ко многим" с моделью Role через таблицу `users_and_roles`.
     * (Пункт 17-18)
     */
    public function roles(): BelongsToMany
    {
        // Указываем связующую таблицу 'users_and_roles', и внешние ключи
        return $this->belongsToMany(Role::class, 'users_and_roles', 'user_id', 'role_id');
        // Если вы планируете мягко удалять связи, можно добавить ->wherePivot('deleted_at', null);
>>>>>>> lb3
    }

    /**
     * Проверяет, обладает ли пользователь определенной ролью по коду роли.
     */
    public function hasRole(string $roleCode): bool
    {
<<<<<<< HEAD
=======
        // Загружает роли, если они еще не загружены, и проверяет наличие роли по коду
>>>>>>> lb3
        return $this->roles->pluck('code')->contains($roleCode);
    }

    /**
     * Проверяет, обладает ли пользователь определенным разрешением по коду разрешения.
<<<<<<< HEAD
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
=======
     * Это проверяет все разрешения, которые есть у всех ролей пользователя.
     * (Пункт 29)
     */
    public function hasPermission(string $permissionCode): bool
    {
        // Загружаем разрешения для всех ролей пользователя,
        // затем объединяем все разрешения в одну плоскую коллекцию и проверяем наличие кода разрешения.
        return $this->roles->flatMap(fn ($role) => $role->permissions)
                           ->pluck('code')
                           ->contains($permissionCode);
>>>>>>> lb3
    }
}
