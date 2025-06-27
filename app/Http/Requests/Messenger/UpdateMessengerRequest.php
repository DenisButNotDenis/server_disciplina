<?php

namespace App\Http\Requests\Messenger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

/**
 * Класс запроса для обновления существующего мессенджера.
 * (Пункт 9)
 */
class UpdateMessengerRequest extends FormRequest
{
    /**
     * Определяет, разрешено ли пользователю выполнять этот запрос.
     * Только администраторы могут обновлять мессенджеры.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->hasPermission('update-messenger');
    }

    /**
     * Возвращает правила валидации, применимые к запросу.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Получаем ID мессенджера из маршрута, чтобы исключить текущий мессенджер из проверки уникальности.
        $messengerId = $this->route('messenger');

        return [
            'name' => [
                'sometimes', // Поле будет валидироваться, только если оно присутствует в запросе
                'string',
                'max:255',
                Rule::unique('messengers', 'name')->ignore($messengerId), // Имя мессенджера должно быть уникальным, исключая текущий
            ],
            'description' => 'nullable|string|max:1000',
            'environment' => [
                'sometimes',
                'string',
                Rule::in(config('messengers.environments')), // Валидация по списку разрешенных окружений
            ],
            'api_key_env_var' => 'nullable|string|max:255',
        ];
    }
}
