<?php

namespace App\Http\Requests\TwoFactor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash; // Для проверки пароля

/**
 * Запрос для включения/отключения двухфакторной авторизации пользователя.
 * (Пункт 3, 4)
 */
class ToggleTwoFactorAuthRequest extends FormRequest
{
    /**
     * Определяет, авторизован ли пользователь для выполнения этого запроса.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::check(); // Только авторизованные пользователи могут менять настройки 2FA
    }

    /**
     * Возвращает правила валидации, применимые к запросу.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'password' => [
                'required', // Текущий пароль обязателен для подтверждения (Пункт 4)
                'string',
                function ($attribute, $value, $fail) {
                    /** @var \App\Models\User $user */
                    $user = Auth::user();
                    if (!Hash::check($value, $user->password)) {
                        $fail('The provided password does not match your current password.');
                    }
                },
            ],
            // При отключении 2FA, требуется также подтверждение кодом (Пункт 4)
            'two_factor_code' => [
                'nullable', // Необязательно, но требуется, если is_2fa_enabled_target = false
                'string',
                'digits:6',
                function ($attribute, $value, $fail) {
                    /** @var \App\Models\User $user */
                    $user = Auth::user();

                    // Если целевое состояние - отключить 2FA и 2FA сейчас включена
                    if ($this->input('is_2fa_enabled_target') === false && $user->twoFactorAuthActive()) {
                        if (empty($value)) {
                            $fail('A 2FA code is required to disable two-factor authentication.');
                        } elseif (!$user->verifyTwoFactorCode($value)) {
                            $fail('The provided 2FA code is invalid or expired.');
                        }
                    }
                },
            ],
            'is_2fa_enabled_target' => 'required|boolean', // Целевое состояние 2FA (true для включения, false для отключения)
        ];
    }
}
