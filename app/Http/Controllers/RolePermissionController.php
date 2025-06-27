<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use App\Http\Requests\AttachRolePermissionRequest; // Запрос для прикрепления
use App\Http\DTOs\RolePermission\RolePermissionResourceDTO; // DTO для связки
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Контроллер для управления связями Role-Permission.
 * (Пункт 14 и 15 для RolesAndPermissions, Пункт 24)
 */
class RolePermissionController extends Controller
{
    /**
     * Прикрепляет разрешение к роли.
     * (POST /api/roles/{role}/permissions)
     * @param Role $role Модель роли из маршрута.
     * @param AttachRolePermissionRequest $request Запрос с permission_id.
     * @return JsonResponse
     */
    public function attachPermission(Role $role, AttachRolePermissionRequest $request): JsonResponse
    {
        $permissionId = $request->validated('permission_id');

        // Проверяем, существует ли разрешение
        $permission = Permission::find($permissionId);
        if (!$permission) {
            throw new NotFoundHttpException('Permission not found.');
        }

        // Проверяем, не назначено ли уже это разрешение роли
        if ($role->permissions()->where('permission_id', $permissionId)->exists()) {
            throw new ConflictHttpException('Role already has this permission.'); // 409 Conflict
        }

        $role->permissions()->attach($permissionId);

        // Возвращаем DTO с данными о связи
        // Для pivot-таблиц DTO может быть упрощен.
        // Здесь мы вернем информацию о роли и ее обновленных разрешениях.
        $role->load('permissions'); // Перезагружаем отношения
        return response()->json([
            'message' => 'Permission attached successfully.',
            'role' => \App\Http\DTOs\Role\RoleResourceDTO::fromModel($role)->toArray()
        ], 200);
    }

    /**
     * Открепляет разрешение от роли.
     * (DELETE /api/roles/{role}/permissions/{permission})
     * @param Role $role Модель роли из маршрута.
     * @param Permission $permission Модель разрешения из маршрута.
     * @return JsonResponse
     */
    public function detachPermission(Role $role, Permission $permission): JsonResponse
    {
        // Проверяем, прикреплено ли это разрешение к данной роли
        if (!$role->permissions()->where('permission_id', $permission->id)->exists()) {
            throw new NotFoundHttpException('Role does not have this permission assigned.');
        }

        $role->permissions()->detach($permission->id);

        // Возвращаем информацию об обновленной роли
        $role->load('permissions'); // Перезагружаем отношения
        return response()->json([
            'message' => 'Permission detached successfully.',
            'role' => \App\Http\DTOs\Role\RoleResourceDTO::fromModel($role)->toArray()
        ], 200);
    }
}
