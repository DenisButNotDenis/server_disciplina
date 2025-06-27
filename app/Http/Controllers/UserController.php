<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\UpdateUserRequest;
use App\Http\DTOs\User\UserResourceDTO;
use App\Http\DTOs\User\UserCollectionDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Импортируем фасад DB для транзакций

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('get-list-user')) {
            return response()->json(['message' => 'Необходимое разрешение: get-list-user'], 403);
        }
        $users = User::with('roles')->get();
        return response()->json(UserCollectionDTO::collect($users)->toArray(), 200);
    }

    public function show(User $user): JsonResponse
    {
        if (Auth::check()) {
            if (Auth::id() === $user->id) {
                // Разрешаем, если это собственные данные
            } elseif (Auth::user()->hasPermission('read-user')) {
                // Разрешаем, если есть разрешение
            } else {
                return response()->json(['message' => 'Необходимое разрешение: read-user'], 403);
            }
        } else {
            return response()->json(['message' => 'Необходимое разрешение: read-user'], 403);
        }
        $user->load('roles');
        return response()->json(UserResourceDTO::fromModel($user)->toArray(), 200);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        // Авторизация уже проверена в UpdateUserRequest::authorize()
        if (!Auth::check() || (Auth::id() !== $user->id && !Auth::user()->hasPermission('update-user'))) {
            return response()->json(['message' => 'Необходимое разрешение: update-user'], 403);
        }

        // Начинаем транзакцию (Пункт 19)
        return DB::transaction(function () use ($request, $user) {
            $user->update($request->validated());
            $user->load('roles');
            return response()->json(UserResourceDTO::fromModel($user)->toArray(), 200);
        });
    }

    public function destroy(User $user): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('delete-user')) {
            return response()->json(['message' => 'Необходимое разрешение: delete-user'], 403);
        }

        // Начинаем транзакцию (Пункт 19)
        return DB::transaction(function () use ($user) {
            $user->delete();
            return response()->json(['message' => 'User deleted successfully.'], 200);
        });
    }

    public function restore(string $id): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('restore-user')) {
            return response()->json(['message' => 'Необходимое разрешение: restore-user'], 403);
        }

        // Начинаем транзакцию (Пункт 19)
        return DB::transaction(function () use ($id) {
            $user = User::withTrashed()->find($id);

            if (!$user) {
                throw new ModelNotFoundException("User not found.");
            }

            if ($user->trashed()) {
                $user->restore();
                $user->load('roles');
                return response()->json(UserResourceDTO::fromModel($user)->toArray(), 200);
            }

            return response()->json(['message' => 'User is not soft deleted.'], 400);
        });
    }
}
