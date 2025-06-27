<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Http\Requests\CreateRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\DTOs\Role\RoleResourceDTO;
use App\Http\DTOs\Role\RoleCollectionDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Импортируем фасад DB для транзакций

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('get-list-role')) {
            return response()->json(['message' => 'Необходимое разрешение: get-list-role'], 403);
        }
        $roles = Role::all();
        return response()->json(RoleCollectionDTO::collect($roles)->toArray(), 200);
    }

    public function show(Role $role): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('read-role')) {
            return response()->json(['message' => 'Необходимое разрешение: read-role'], 403);
        }
        return response()->json(RoleResourceDTO::fromModel($role)->toArray(), 200);
    }

    public function store(CreateRoleRequest $request): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('create-role')) {
            return response()->json(['message' => 'Необходимое разрешение: create-role'], 403);
        }

        // Начинаем транзакцию (Пункт 19)
        return DB::transaction(function () use ($request) {
            $role = Role::create($request->validated());
            return response()->json(RoleResourceDTO::fromModel($role)->toArray(), 201);
        });
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('update-role')) {
            return response()->json(['message' => 'Необходимое разрешение: update-role'], 403);
        }

        // Начинаем транзакцию (Пункт 19)
        return DB::transaction(function () use ($request, $role) {
            $role->update($request->validated());
            return response()->json(RoleResourceDTO::fromModel($role)->toArray(), 200);
        });
    }

    public function destroy(Role $role): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('delete-role')) {
            return response()->json(['message' => 'Необходимое разрешение: delete-role'], 403);
        }

        // Начинаем транзакцию (Пункт 19)
        return DB::transaction(function () use ($role) {
            $role->delete(); // Мягкое удаление
            return response()->json(['message' => 'Role deleted successfully.'], 200);
        });
    }

    public function restore(string $id): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('restore-role')) {
            return response()->json(['message' => 'Необходимое разрешение: restore-role'], 403);
        }

        // Начинаем транзакцию (Пункт 19)
        return DB::transaction(function () use ($id) {
            $role = Role::withTrashed()->find($id);

            if (!$role) {
                throw new ModelNotFoundException("Role not found.");
            }

            if ($role->trashed()) {
                $role->restore();
                return response()->json(RoleResourceDTO::fromModel($role)->toArray(), 200);
            }

            return response()->json(['message' => 'Role is not soft deleted.'], 400);
        });
    }
}
