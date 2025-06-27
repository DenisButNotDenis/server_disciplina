<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
    ];

    /**
     * Атрибуты, которые должны быть скрыты при сериализации.
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Атрибуты, которые должны быть приведены к определенным типам.
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
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
    }

    /**
     * Проверяет, обладает ли пользователь определенной ролью по коду роли.
     */
    public function hasRole(string $roleCode): bool
    {
        // Загружает роли, если они еще не загружены, и проверяет наличие роли по коду
        return $this->roles->pluck('code')->contains($roleCode);
    }

    /**
     * Проверяет, обладает ли пользователь определенным разрешением по коду разрешения.
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
    }
}
