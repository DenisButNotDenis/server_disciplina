<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Auth;

/**
 * Класс запроса для прикрепления разрешения к роли.
 * (Пункт 24 - Реализовать создание связки роли с разрешением)
 */
class AttachRolePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Только авторизованные пользователи могут назначать разрешения ролям.
        // Позже здесь будет проверка на разрешение, например, 'create-role-permission'.
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'role_id' => [
                'required',
                'integer',
                'exists:roles,id', // Убеждаемся, что роль существует
            ],
            'permission_id' => [
                'required',
                'integer',
                'exists:permissions,id', // Убеждаемся, что разрешение существует
                // Проверяем, что комбинация role_id и permission_id уникальна
                // (роль не может иметь одно и то же разрешение несколько раз)
                Rule::unique('roles_and_permissions')->where(function ($query) {
                    return $query->where('role_id', $this->input('role_id'));
                }),
            ],
        ];
    }

    public function toRolePermissionData(): array
    {
        return $this->validated();
    }
}
