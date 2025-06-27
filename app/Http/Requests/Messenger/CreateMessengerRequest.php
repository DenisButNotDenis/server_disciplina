<?php

namespace App\Http\Requests\Messenger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

/**
 * Класс запроса для создания нового мессенджера.
 * (Пункт 9)
 */
class CreateMessengerRequest extends FormRequest
{
    /**
     * Определяет, разрешено ли пользователю выполнять этот запрос.
     * Только администраторы могут создавать мессенджеры.
     */
    public function authorize(): bool
    {
        // Для создания мессенджеров требуется специальное разрешение.
        return Auth::check() && Auth::user()->hasPermission('create-messenger');
    }

    /**
     * Возвращает правила валидации, применимые к запросу.
     * (Пункт 7.a)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('messengers', 'name'), // Имя мессенджера должно быть уникальным
            ],
            'description' => 'nullable|string|max:1000',
            'environment' => [
                'required',
                'string',
                Rule::in(config('messengers.environments')), // Валидация по списку разрешенных окружений
            ],
            'api_key_env_var' => 'nullable|string|max:255', // Имя переменной окружения для API ключа
        ];
    }
}