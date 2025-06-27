<?php

namespace App\Http\Requests\Messenger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\UserMessenger; // Для проверки существования связи
use App\Models\User; // Для получения пользователя из маршрута

/**
 * Класс запроса для подтверждения связи пользователя с мессенджером.
 * (Пункт 10, 11)
 */
class VerifyUserMessengerRequest extends FormRequest
{
    /**
     * Определяет, разрешено ли пользователю выполнять этот запрос.
     * Только владелец связи может ее подтвердить.
     */
    public function authorize(): bool
    {
        /** @var User $userFromRoute */
        $userFromRoute = $this->route('user'); // Пользователь, для которого выполняем операцию
        /** @var UserMessenger $userMessenger */
        $userMessenger = $this->route('userMessenger'); // Связь из маршрута

        // Проверяем, что текущий авторизованный пользователь является владельцем этой связи
        return Auth::check() && Auth::id() === $userFromRoute->id && $userMessenger->user_id === Auth::id();
    }

    /**
     * Возвращает правила валидации, применимые к запросу.
     * (Пункт 10, 11)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Код подтверждения, который пользователь получил в мессенджере
            'verification_code' => 'required|string|max:255',
        ];
    }
}
