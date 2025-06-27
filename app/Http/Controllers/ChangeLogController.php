<?php

namespace App\Http\Controllers;

use App\Models\ChangeLog;
use App\Models\User; // Для отката
use App\Models\Role; // Для отката
use App\Models\Permission; // Для отката
use App\Http\DTOs\ChangeLog\ChangeLogResourceDTO;
use App\Http\DTOs\ChangeLog\ChangeLogCollectionDTO;
use App\Http\DTOs\User\UserResourceDTO; // Для отката пользователя
use App\Http\DTOs\Role\RoleResourceDTO; // Для отката роли
use App\Http\DTOs\Permission\PermissionResourceDTO; // Для отката разрешения
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Для транзакций
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException; // Для 403
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException; // Для 400

/**
 * Контроллер для управления логами изменений.
 * (Пункт 12, 13)
 */
class ChangeLogController extends Controller
{
    /**
     * Получить список всех логов изменений.
     * (GET /api/change-logs)
     * (Пункт 17, 18 - Проверка доступа: get-story-all)
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        // Для просмотра всех логов, обычно требуется разрешение администратора.
        if (!Auth::check() || !Auth::user()->hasPermission('get-story-all')) {
            throw new AccessDeniedHttpException('Необходимое разрешение: get-story-all');
        }

        $changeLogs = ChangeLog::orderBy('created_at', 'desc')->get();
        // Используем fromModel() для каждого DTO, чтобы применить логику diff
        return response()->json(ChangeLogCollectionDTO::collect($changeLogs)->toArray(), 200);
    }

    /**
     * Получить историю изменений для конкретного пользователя.
     * (GET /api/users/{user}/history)
     * (Пункт 11, 17, 18 - Проверка доступа: get-story-user)
     * @param User $user Модель пользователя.
     * @return JsonResponse
     */
    public function showUserHistory(User $user): JsonResponse
    {
        // Проверка разрешения:
        // Пользователь может видеть свою собственную историю ИЛИ
        // пользователь с разрешением 'get-story-user' может видеть историю любого пользователя.
        if (Auth::check()) {
            if (Auth::id() === $user->id) {
                // Разрешено для своих данных
            } elseif (Auth::user()->hasPermission('get-story-user')) {
                // Разрешено по разрешению
            } else {
                throw new AccessDeniedHttpException('Необходимое разрешение: get-story-user');
            }
        } else {
            throw new AccessDeniedHttpException('Необходимое разрешение: get-story-user');
        }

        $history = ChangeLog::where('mutatable_type', User::class)
                            ->where('mutatable_id', $user->id)
                            ->orderBy('created_at', 'desc')
                            ->get();

        return response()->json(ChangeLogCollectionDTO::collect($history)->toArray(), 200);
    }

    /**
     * Получить историю изменений для конкретной роли.
     * (GET /api/roles/{role}/history)
     * (Пункт 11, 17, 18 - Проверка доступа: get-story-role)
     * @param Role $role Модель роли.
     * @return JsonResponse
     */
    public function showRoleHistory(Role $role): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('get-story-role')) {
            throw new AccessDeniedHttpException('Необходимое разрешение: get-story-role');
        }

        $history = ChangeLog::where('mutatable_type', Role::class)
                            ->where('mutatable_id', $role->id)
                            ->orderBy('created_at', 'desc')
                            ->get();

        return response()->json(ChangeLogCollectionDTO::collect($history)->toArray(), 200);
    }

    /**
     * Получить историю изменений для конкретного разрешения.
     * (GET /api/permissions/{permission}/history)
     * (Пункт 11, 17, 18 - Проверка доступа: get-story-permission)
     * @param Permission $permission Модель разрешения.
     * @return JsonResponse
     */
    public function showPermissionHistory(Permission $permission): JsonResponse
    {
        if (!Auth::check() || !Auth::user()->hasPermission('get-story-permission')) {
            throw new AccessDeniedHttpException('Необходимое разрешение: get-story-permission');
        }

        $history = ChangeLog::where('mutatable_type', Permission::class)
                            ->where('mutatable_id', $permission->id)
                            ->orderBy('created_at', 'desc')
                            ->get();

        return response()->json(ChangeLogCollectionDTO::collect($history)->toArray(), 200);
    }

    /**
     * Реализовать механизм возврата записи сущности к состоянию в записи до выполнения мутации.
     * (Пункт 21)
     * @param ChangeLog $changeLog Запись лога, к которой нужно откатиться.
     * @return JsonResponse
     */
    public function revert(ChangeLog $changeLog): JsonResponse
    {
        // Проверка разрешения: только админ или тот, у кого есть 'revert-changelog'
        if (!Auth::check() || !Auth::user()->hasPermission('revert-changelog')) {
            throw new AccessDeniedHttpException('Необходимое разрешение: revert-changelog');
        }

        // Убедимся, что это лог обновления или создания, который можно откатить
        if (!in_array($changeLog->event, ['updated', 'created'])) {
            throw new BadRequestHttpException('Can only revert "updated" or "created" change logs.');
        }

        // Начинаем транзакцию для атомарности отката (Пункт 19)
        return DB::transaction(function () use ($changeLog) {
            $modelClass = $changeLog->mutatable_type;
            $modelId = $changeLog->mutatable_id;

            // Находим мутировавшую модель
            // withTrashed() чтобы найти ее, даже если она была мягко удалена
            $model = $modelClass::withTrashed()->find($modelId);

            if (!$model) {
                throw new ModelNotFoundException("Mutated entity of type {$modelClass} with ID {$modelId} not found.");
            }

            // Значения, к которым нужно откатиться
            $revertToValues = $changeLog->old_values;

            if ($changeLog->event === 'created') {
                // Если запись была создана, откат означает её удаление.
                // Для ЛР4, это будет жесткое удаление, чтобы убрать запись полностью.
                $model->forceDelete();
                return response()->json(['message' => 'Successfully reverted "created" event by deleting the record.'], 200);
            } elseif ($changeLog->event === 'updated') {
                // Если запись была обновлена, откатываемся к старым значениям.
                // Применяем старые значения
                $model->fill($revertToValues);
                $model->save(); // Сохраняем модель с старыми значениями

                // Обработка SoftDeletes при откате
                if ($model->trashed() && (empty($revertToValues['deleted_at']) || $revertToValues['deleted_at'] === null)) {
                    // Если сейчас удалена, но откатываемся к состоянию "не удалена"
                    $model->restore();
                } elseif (!$model->trashed() && !empty($revertToValues['deleted_at'])) {
                    // Если сейчас активна, но откатываемся к состоянию "удалена"
                    $model->delete();
                }

                // В зависимости от типа модели, используем соответствующий DTO
                $revertedDTO = null;
                switch ($modelClass) {
                    case User::class:
                        $model->load('roles'); // Загружаем роли для DTO пользователя
                        $revertedDTO = UserResourceDTO::fromModel($model)->toArray();
                        break;
                    case Role::class:
                        $revertedDTO = RoleResourceDTO::fromModel($model)->toArray();
                        break;
                    case Permission::class:
                        $revertedDTO = PermissionResourceDTO::fromModel($model)->toArray();
                        break;
                    default:
                        $revertedDTO = $model->toArray();
                        break;
                }

                return response()->json([
                    'message' => 'Successfully reverted "updated" event.',
                    'reverted_entity' => $revertedDTO
                ], 200);
            }

            return response()->json(['message' => 'Revert not applicable for this log event.'], 400);
        });
    }
}