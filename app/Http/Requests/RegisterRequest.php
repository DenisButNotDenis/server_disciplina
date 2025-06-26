<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // Для правила уникальности
use Carbon\Carbon; // Для удобной работы с датами (Laravel использует Carbon для дат)
use App\Http\DTOs\Auth\RegisterResourceDTO; 

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:7',
                'regex:/^[A-Z][a-zA-Z]*$/',
                // Проверяем, что username уникален в таблице 'users', игнорируя регистр
                Rule::unique('users')->where(fn ($query) => $query->whereRaw('LOWER(username) = ?', [strtolower($this->username)])),
            ],
            'email' => [
                'required',
                'string',
                'email', // Проверяет, что это корректный формат email
                Rule::unique('users', 'email'), // Проверяет, что email уникален в таблице 'users'
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[0-9]/',
                'regex:/[^a-zA-Z0-9]/',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'confirmed', // Проверяет, что есть поле c_password и оно совпадает с password
            ],
            'password_confirmation' => 'required', // Просто обязательное поле (проверка совпадения идет через 'confirmed' в password)
            'birthday' => [
                'required',
                'date_format:Y-m-d', // Требует формат "год-месяц-день" (например, 2000-12-31)
                // Проверяет, что дата рождения не раньше 14 лет назад от текущей даты (т.е. возраст >= 14)
                'before_or_equal:' . Carbon::now()->subYears(14)->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Возвращает данные запроса в виде DTO.
     */
    public function toResourceDTO(): RegisterResourceDTO
    {
        return new RegisterResourceDTO(
            username: $this->input('username'),
            email: $this->input('email'),
            password: $this->input('password'),
            birthday: Carbon::parse($this->input('birthday')) // Преобразуем строку даты в объект Carbon
        );
    }
}