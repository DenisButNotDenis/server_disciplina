<?php

namespace App\Http\Requests\TwoFactor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth; // Для проверки авторизации
use Illuminate\Support\Facades\Cache; // Для работы с кэшем
use App\Models\User; // Для подсказки IDE

/**
 * Запрос для подтверждения кода двухфакторной аутентификации.
 * (Пункт 15)
 */
class VerifyTwoFactorCodeRequest extends FormRequest
{
    /**
     * Определяет, авторизован ли пользователь для выполнения этого запроса.
     * Здесь мы проверяем наличие временного 2FA токена.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Для этого запроса не используется стандартная авторизация по Access Token.
        // Вместо этого используется временный токен, полученный на шаге логина (Пункт 14, 15).
        $twoFactorToken = $this->input('two_factor_token');

        if (!$twoFactorToken) {
            return false;
        }

        // Проверяем, существует ли этот временный токен в кэше и связан ли он с пользователем.
        $userId = Cache::get('2fa_temp_token:' . $twoFactorToken);

        if (!$userId) {
            return false; // Токен не найден или истек
        }

        // Сохраняем ID пользователя в запросе для дальнейшего использования в контроллере
        $this->merge(['user_id_from_2fa_token' => $userId]);

        return true;
    }

    /**
     * Возвращает правила валидации, применимые к запросу.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'two_factor_code' => 'required|string|digits:6', // Код из 6 цифр
            'two_factor_token' => 'required|string', // Временный токен для 2FA операций
        ];
    }
}
