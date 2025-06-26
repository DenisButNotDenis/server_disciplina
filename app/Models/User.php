<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str; // Нужен для генерации случайных строк для токенов
use Illuminate\Support\Facades\Hash; // Нужен для хэширования (шифрования) токенов и паролей
use Carbon\Carbon; // Для работы с датами

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'birthday',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birthday' => 'date', 
        ];
    }
    /**
     * Связь модели User с моделью AccessToken.
     * Один пользователь может иметь много токенов доступа.
     */
    public function accessTokens()
    {
        return $this->hasMany(AccessToken::class);
    }

    /**
     * Генерирует новый токен доступа для пользователя.
     *
     * @param int $expirationMinutes Время, через сколько минут токен "умрет".
     * @return string Сам токен (незахэшированный), который мы выдадим клиенту.
     */
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
}
