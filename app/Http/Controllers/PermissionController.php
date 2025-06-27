<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Http\Requests\CreatePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Http\DTOs\Permission\PermissionResourceDTO;
use App\Http\DTOs\Permission\PermissionCollectionDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Импортируем фасад DB для транзакций

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('get-list-permission')) {
            return response()->json(['message' => 'Необходимое разрешение: get-list-permission'], 403);
        }
        $permissions = Permission::all();
        return response()->json(PermissionCollectionDTO::collect($permissions)->toArray(), 200);
    }

    public function show(Permission $permission): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('read-permission')) {
            return response()->json(['message' => 'Необходимое разрешение: read-permission'], 403);
        }
        return response()->json(PermissionResourceDTO::fromModel($permission)->toArray(), 200);
    }

    public function store(CreatePermissionRequest $request): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('create-permission')) {
            return response()->json(['message' => 'Необходимое разрешение: create-permission'], 403);
        }

        // Начинаем транзакцию (Пункт 19)
        return DB::transaction(function () use ($request) {
            $permission = Permission::create($request->validated());
            return response()->json(PermissionResourceDTO::fromModel($permission)->toArray(), 201);
        });
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('update-permission')) {
            return response()->json(['message' => 'Необходимое разрешение: update-permission'], 403);
        }

        // Начинаем транзакцию (Пункт 19)
        return DB::transaction(function () use ($request, $permission) {
            $permission->update($request->validated());
            return response()->json(PermissionResourceDTO::fromModel($permission)->toArray(), 200);
        });
    }

    public function destroy(Permission $permission): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('delete-permission')) {
            return response()->json(['message' => 'Необходимое разрешение: delete-permission'], 403);
        }

        // Начинаем транзакцию (Пункт 19)
        return DB::transaction(function () use ($permission) {
            $permission->delete(); // Мягкое удаление
            return response()->json(['message' => 'Permission deleted successfully.'], 200);
        });
    }

    public function restore(string $id): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('restore-permission')) {
            return response()->json(['message' => 'Необходимое разрешение: restore-permission'], 403);
        }

        // Начинаем транзакцию (Пункт 19)
        return DB::transaction(function () use ($id) {
            $permission = Permission::withTrashed()->find($id);

            if (!$permission) {
                throw new ModelNotFoundException("Permission not found.");
            }

            if ($permission->trashed()) {
                $permission->restore();
                return response()->json(PermissionResourceDTO::fromModel($permission)->toArray(), 200);
            }

            return response()->json(['message' => 'Permission is not soft deleted.'], 400);
        });
    }
}
