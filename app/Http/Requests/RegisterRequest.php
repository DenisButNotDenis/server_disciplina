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
                'min:7', // Минимум 7 символов
                'regex:/^[A-Z][a-zA-Z]*$/', // Начинается с заглавной латинской буквы, только латинские буквы
                Rule::unique('users', 'username')->where(function ($query) {
                    $query->whereNull('deleted_at'); // Игнорируем мягко удаленных пользователей при проверке уникальности
                }),
            ],
            'email' => [
                'required',
                'string',
                'email', // Проверяет формат email
                'max:255',
                Rule::unique('users', 'email')->where(function ($query) {
                    $query->whereNull('deleted_at'); // Игнорируем мягко удаленных пользователей
                }),
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[0-9]/', // Минимум одна цифра
                'regex:/[^a-zA-Z0-9]/', // Минимум один спецсимвол
                'regex:/[A-Z]/', // Минимум одна заглавная буква
                'regex:/[a-z]/', // Минимум одна строчная буква
                'confirmed', // Проверяет, что есть поле `<field>_confirmation` (password_confirmation) и оно совпадает
            ],
            'password_confirmation' => 'required', // Поле для подтверждения пароля, требуемое 'confirmed'
            'birthday' => [
                'required',
                'date_format:Y-m-d', // Формат даты YYYY-MM-DD
                'before_or_equal:' . \Carbon\Carbon::now()->subYears(14)->format('Y-m-d'), // Возраст не менее 14 лет
            ],
        ];
    }
}