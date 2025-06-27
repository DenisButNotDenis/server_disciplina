<?php

namespace App\Http\Requests\Messenger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Models\User; // Для получения пользователя, к которому прикрепляем
use App\Models\Messenger; // Для проверки существования мессенджера

/**
 * Класс запроса для прикрепления мессенджера к пользователю.
 * (Пункт 9)
 */
class AttachUserMessengerRequest extends FormRequest
{
    /**
     * Определяет, разрешено ли пользователю выполнять этот запрос.
     * Пользователь может прикрепить мессенджер к себе, или администратор может прикрепить к другому пользователю.
     * (Пункт 6.b)
     */
    public function authorize(): bool
    {
        /** @var User $userFromRoute */
        $userFromRoute = $this->route('user'); // Пользователь, для которого выполняем операцию

        // Если текущий авторизованный пользователь пытается прикрепить мессенджер к СЕБЕ
        if (Auth::check() && Auth::id() === $userFromRoute->id) {
            return true;
        }

        // Или если текущий авторизованный пользователь - админ и имеет разрешение 'attach-user-messenger'
        return Auth::check() && Auth::user()->hasPermission('attach-user-messenger');
    }

    /**
     * Возвращает правила валидации, применимые к запросу.
     * (Пункт 6.b, 7.b)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $userFromRoute */
        $userFromRoute = $this->route('user'); // Пользователь, для которого выполняется операция

        return [
            'messenger_id' => [
                'required',
                'integer',
                // Проверяем, что мессенджер существует
                'exists:messengers,id',
                // Пункт 6.b: Пользователь может выбирать мессенджеры. Только из списка соответствующей ему среды окружения.
                Rule::exists('messengers', 'id')->where(function ($query) {
                    $query->where('environment', config('app.env'));
                }),
                // Проверяем, что связка user_id, messenger_id и messenger_user_id уникальна
                Rule::unique('users_and_messengers')->where(function ($query) use ($userFromRoute) {
                    $query->where('user_id', $userFromRoute->id);
                    $query->where('messenger_id', $this->input('messenger_id'));
                    // Если messenger_user_id может быть уникальным только для конкретного мессенджера
                    $query->where('messenger_user_id', $this->input('messenger_user_id'));
                }),
            ],
            'messenger_user_id' => 'required|string|max:255', // Идентификатор/имя пользователя в мессенджере
            'allow_notifications' => 'boolean', // Флаг разрешения уведомления (опционально, по умолчанию true)
        ];
    }
}
