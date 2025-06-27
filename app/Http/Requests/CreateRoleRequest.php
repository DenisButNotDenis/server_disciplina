<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // Для правила уникальности
use App\Http\DTOs\Role\RoleResourceDTO; // Импортируем RoleResourceDTO
use Illuminate\Support\Facades\Auth; // Для проверки авторизации

/**
 * Класс запроса для создания новой роли.
 * (Пункт 11 и 12)
 */
class CreateRoleRequest extends FormRequest
{
    /**
     * Определяет, авторизован ли пользователь для выполнения этого запроса.
     * Здесь мы будем использовать механизм разрешений (permissions), который реализуем позже.
     * Пока для простоты, пусть только авторизованные пользователи могут создавать роли.
     * (Пункт 11 - Проверка авторизации обеспечивается на уровне класса запроса)
     */
    public function authorize(): bool
    {
        // Пока что, разрешаем только авторизованным пользователям.
        // Позже здесь будет проверка на конкретное разрешение, например, 'create-role'.
        return Auth::check();
    }

    /**
     * Возвращает правила валидации, применимые к запросу.
     * (Пункт 11 - Проверка на уникальность данных)
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where(function ($query) {
                    $query->whereNull('deleted_at'); // Игнорируем мягко удаленные записи
                }),
            ],
            'code' => [
                'required',
                'string',
                'max:255',
                'alpha_dash', // Буквы, цифры, тире и подчеркивания
                Rule::unique('roles', 'code')->where(function ($query) {
                    $query->whereNull('deleted_at'); // Игнорируем мягко удаленные записи
                }),
            ],
            'description' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Добавляет метод возвращающий экземпляр соответствующего DTO ресурса.
     * (Пункт 12)
     */
    public function toRoleResourceDTO(): array
    {
        // В данном случае, так как это создание, мы просто возвращаем проверенные данные,
        // DTO будет создан из модели после ее сохранения.
        return $this->validated();
    }
}
