<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\UpdateUserRequest;
use App\Http\DTOs\User\UserResourceDTO;
use App\Http\DTOs\User\UserCollectionDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
            } elseif (Auth::user()->hasPermission('read-user')) {
            } else {
                return response()->json(['message' => 'Необходимое разрешение: read-user'], 403);
            }
        } else {
            return response()->json(['message' => 'Необходимое разрешение: read-user'], 403);
        }
        $user->load('roles');
        return response()->json(UserResourceDTO::fromModel($user)->toArray(), 200);
    }

    /**
     * Обновить существующего пользователя.
     * (Пункт 12 - Уведомление при Изменении данных)
     *
     * @param UpdateUserRequest $request
     * @param User $user
     * @return JsonResponse
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        if (!Auth::check() || (Auth::id() !== $user->id && !Auth::user()->hasPermission('update-user'))) {
            return response()->json(['message' => 'Необходимое разрешение: update-user'], 403);
        }

        $oldUserData = $user->toArray(); // Сохраняем данные до обновления

        return DB::transaction(function () use ($request, $user, $oldUserData) {
            $user->update($request->validated());

            // Определяем, какие поля изменились для уведомления
            $changedFields = array_diff_assoc($user->toArray(), $oldUserData);
            unset($changedFields['updated_at']); // Игнорируем updated_at

            if (!empty($changedFields)) {
                $message = "Ваши данные аккаунта '{$user->username}' были обновлены. Изменения: " . json_encode($changedFields, JSON_UNESCAPED_UNICODE);
                $user->sendMessengerNotification($message, 'user_updated');
            }

            $user->load('roles');
            return response()->json(UserResourceDTO::fromModel($user)->toArray(), 200);
        });
    }

    /**
     * Удалить пользователя (мягкое удаление).
     * (Пункт 12 - Уведомление при Изменении данных)
     *
     * @param User $user
     * @return JsonResponse
     */
    public function destroy(User $user): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('delete-user')) {
            return response()->json(['message' => 'Необходимое разрешение: delete-user'], 403);
        }

        return DB::transaction(function () use ($user) {
            $user->delete(); // Мягкое удаление

            // Уведомление об удалении (Пункт 12)
            $user->sendMessengerNotification("Ваш аккаунт '{$user->username}' был удален. Вы можете восстановить его в течение 30 дней.", 'user_deleted');

            return response()->json(['message' => 'User deleted successfully.'], 200);
        });
    }

    /**
     * Восстановить мягко удаленного пользователя.
     * (Пункт 12 - Уведомление при Изменении данных)
     *
     * @param string $id
     * @return JsonResponse
     */
    public function restore(string $id): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('restore-user')) {
            return response()->json(['message' => 'Необходимое разрешение: restore-user'], 403);
        }

        return DB::transaction(function () use ($id) {
            $user = User::withTrashed()->find($id);

            if (!$user) {
                throw new ModelNotFoundException("User not found.");
            }

            if ($user->trashed()) {
                $user->restore();
                // Уведомление о восстановлении (Пункт 12)
                $user->sendMessengerNotification("Ваш аккаунт '{$user->username}' был успешно восстановлен.", 'user_restored');
                $user->load('roles');
                return response()->json(UserResourceDTO::fromModel($user)->toArray(), 200);
            }

            return response()->json(['message' => 'User is not soft deleted.'], 400);
        });
    }
}