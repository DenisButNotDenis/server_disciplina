<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\DTOs\Auth\LoginResourceDTO; 

class LoginRequest extends FormRequest
{
    /**
     * Определяет, разрешено ли пользователю выполнять этот запрос.
     * Для входа и регистрации, обычно, всем разрешено.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Определяет правила валидации (проверки) для входных данных.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => [
                'required', // Обязательное поле
                'string',   // Должно быть строкой
                'min:7',    // Минимум 7 символов
                'regex:/^[A-Z][a-zA-Z]*$/', // Начинается с большой буквы латинского алфавита, содержит только латинские буквы
            ],
            'password' => [
                'required',
                'string',
                'min:8',      // Минимум 8 символов
                'regex:/[0-9]/',       // Должна быть хотя бы 1 цифра
                'regex:/[^a-zA-Z0-9]/', // Должен быть хотя бы 1 символ (не буква и не цифра)
                'regex:/[A-Z]/',       // Должна быть хотя бы 1 заглавная буква
                'regex:/[a-z]/',       // Должна быть хотя бы 1 строчная буква
            ],
        ];
    }

    /**
     * Возвращает данные запроса в виде DTO.
     * Это чистый и типизированный способ получить проверенные данные.
     */
    public function toResourceDTO(): LoginResourceDTO
    {
        return new LoginResourceDTO(
            username: $this->input('username'), // Получаем проверенное имя пользователя
            password: $this->input('password')  // Получаем проверенный пароль
        );
    }
}