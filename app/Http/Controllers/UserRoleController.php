<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Http\Requests\AttachUserRoleRequest;
use App\Http\DTOs\User\UserResourceDTO; // Импортируем DTO пользователя для возврата
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class UserRoleController extends Controller
{
    /**
     * Прикрепляет роль к пользователю.
     * (Пункт 12 - Уведомление при Назначении ролей)
     *
     * @param User $user
     * @param AttachUserRoleRequest $request
     * @return JsonResponse
     */
    public function attachRole(User $user, AttachUserRoleRequest $request): JsonResponse
    {
        // Авторизация проверена в AttachUserRoleRequest::authorize()
        if (!Auth::check() || !Auth::user()->hasPermission('create-user_role')) {
             return response()->json(['message' => 'Необходимое разрешение: create-user_role'], 403);
        }

        $roleId = $request->validated('role_id');
        $role = Role::find($roleId);

        if (!$role) {
            throw new NotFoundHttpException('Role not found.');
        }

        if ($user->roles()->where('role_id', $roleId)->exists()) {
            throw new ConflictHttpException('User already has this role.');
        }

        return DB::transaction(function () use ($user, $role) {
            $user->roles()->attach($role->id);

            // Уведомление о назначении роли (Пункт 12)
            $user->sendMessengerNotification("Вам была назначена новая роль: '{$role->name}'.", 'role_assigned');

            $user->load('roles');
            return response()->json([
                'message' => 'Role attached successfully.',
                'user' => UserResourceDTO::fromModel($user)->toArray()
            ], 200);
        });
    }

    /**
     * Открепляет роль от пользователя.
     * (Пункт 12 - Уведомление при Назначении ролей)
     *
     * @param User $user
     * @param Role $role
     * @return JsonResponse
     */
    public function detachRole(User $user, Role $role): JsonResponse
    {
        // Авторизация проверена в UserRoleController::authorize() (если она есть)
        if (!Auth::check() || !Auth::user()->hasPermission('delete-user_role')) {
            return response()->json(['message' => 'Необходимое разрешение: delete-user_role'], 403);
        }

        if (!$user->roles()->where('role_id', $role->id)->exists()) {
            throw new NotFoundHttpException('User does not have this role assigned.');
        }

        return DB::transaction(function () use ($user, $role) {
            $user->roles()->detach($role->id);

            // Уведомление об откреплении роли (Пункт 12)
            $user->sendMessengerNotification("Роль '{$role->name}' была удалена из вашего аккаунта.", 'role_detached');

            $user->load('roles');
            return response()->json([
                'message' => 'Role detached successfully.',
                'user' => UserResourceDTO::fromModel($user)->toArray()
            ], 200);
        });
    }
}
