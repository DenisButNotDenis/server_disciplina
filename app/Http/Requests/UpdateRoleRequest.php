<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // Для правила уникальности
use App\Http\DTOs\Role\RoleResourceDTO; // Импортируем RoleResourceDTO
use Illuminate\Support\Facades\Auth; // Для проверки авторизации

/**
 * Класс запроса для обновления существующей роли.
 * (Пункт 11 и 12)
 */
class UpdateRoleRequest extends FormRequest
{
    /**
     * Определяет, авторизован ли пользователь для выполнения этого запроса.
     * (Пункт 11 - Проверка авторизации обеспечивается на уровне класса запроса)
     */
    public function authorize(): bool
    {
        // Пока что, разрешаем только авторизованным пользователям.
        // Позже здесь будет проверка на конкретное разрешение, например, 'update-role'.
        return Auth::check();
    }

    /**
     * Возвращает правила валидации, применимые к запросу.
     * (Пункт 11 - Проверка на уникальность данных)
     */
    public function rules(): array
    {
        // Получаем ID роли из маршрута, чтобы исключить текущую роль из проверки уникальности
        $roleId = $this->route('role'); // Предполагаем, что параметр маршрута называется 'role'

        return [
            'name' => [
                'sometimes', // 'sometimes' означает, что поле будет валидироваться, только если оно присутствует в запросе
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->ignore($roleId) // Игнорируем текущую роль по ID
                    ->where(function ($query) {
                        $query->whereNull('deleted_at'); // Игнорируем мягко удаленные записи
                    }),
            ],
            'code' => [
                'sometimes',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('roles', 'code')
                    ->ignore($roleId) // Игнорируем текущую роль по ID
                    ->where(function ($query) {
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
        // В данном случае, так как это обновление, мы просто возвращаем проверенные данные.
        return $this->validated();
    }
}