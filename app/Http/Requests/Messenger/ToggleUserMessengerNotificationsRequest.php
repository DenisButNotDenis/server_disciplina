<?php

namespace App\Http\Requests\Messenger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\UserMessenger;
use App\Models\User;

/**
 * Класс запроса для изменения флага разрешения уведомлений для связи мессенджера пользователя.
 * (Пункт 7.b.vi)
 */
class ToggleUserMessengerNotificationsRequest extends FormRequest
{
    /**
     * Определяет, разрешено ли пользователю выполнять этот запрос.
     * Только владелец связи может изменить этот флаг, или администратор.
     */
    public function authorize(): bool
    {
        /** @var User $userFromRoute */
        $userFromRoute = $this->route('user'); // Пользователь, для которого выполняем операцию
        /** @var UserMessenger $userMessenger */
        $userMessenger = $this->route('userMessenger'); // Связь из маршрута

        // Если текущий авторизованный пользователь является владельцем этой связи ИЛИ
        // является администратором с разрешением 'manage-user-messenger-notifications'
        return Auth::check() && (
            (Auth::id() === $userFromRoute->id && $userMessenger->user_id === Auth::id()) ||
            Auth::user()->hasPermission('manage-user-messenger-notifications')
        );
    }

    /**
     * Возвращает правила валидации, применимые к запросу.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'allow_notifications' => 'required|boolean', // Целевое состояние флага
        ];
    }
}