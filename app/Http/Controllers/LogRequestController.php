<?php

namespace App\Http\Controllers;

use App\Models\LogRequest; // Модель для логов запросов
use App\Http\DTOs\LogRequest\LogRequestCollectionDTO; // Коллекция DTO для логов
use App\Http\DTOs\LogRequest\LogRequestResourceDTO; // DTO для одного лога
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request; // Для получения параметров запроса
use Illuminate\Support\Facades\Auth; // Для проверки авторизации/разрешений
use Illuminate\Database\Eloquent\ModelNotFoundException; // Для обработки 404
use Illuminate\Support\Carbon; // Для работы с датами и очистки старых логов

/**
 * Контроллер для управления логами запросов пользователей.
 * (Пункт 6.b, 12, 13, 14, 15, 16)
 */
class LogRequestController extends Controller
{
    /**
     * Получить список логов запросов с фильтрацией, сортировкой и пагинацией.
     * (Пункт 6.b, 12, 13, 15, 16)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Пункт 6.b: Администратор приложения может получить информацию по логам.
        // Здесь потребуется проверка разрешения, например, 'view-logs'.
        // Пока что просто проверяем, что пользователь авторизован и является админом (если есть такая роль).
        // Позже мы добавим реальную проверку разрешения через Middleware или метод hasPermission.
        if (!Auth::check() || !Auth::user()->hasPermission('view-request-logs')) {
            return response()->json(['message' => 'Недостаточно прав. Необходимо разрешение "view-request-logs".'], 403);
        }

        $query = LogRequest::query();

        // Пункт 6.c: Записи логов хранятся в системе не более 73 часов.
        // Добавляем условие для удаления старых записей
        $query->where('requested_at', '>=', Carbon::now()->subHours(73));

        // Пункт 16.b: filter – Массив с правилами фильтрации списка.
        if ($request->has('filter')) {
            $filters = json_decode($request->input('filter'), true);
            if (is_array($filters)) {
                foreach ($filters as $filter) {
                    if (isset($filter['key']) && isset($filter['value'])) {
                        switch ($filter['key']) {
                            case 'user_id':
                            case 'response_status':
                            case 'ip_address':
                            case 'user_agent':
                            case 'controller_path':
                                $query->where($filter['key'], $filter['value']);
                                break;
                            case 'search': // Пример поиска по нескольким полям
                                $searchValue = '%' . $filter['value'] . '%';
                                $query->where(function ($q) use ($searchValue) {
                                    $q->where('full_url', 'like', $searchValue)
                                      ->orWhere('user_agent', 'like', $searchValue)
                                      ->orWhere('ip_address', 'like', $searchValue)
                                      ->orWhere('controller_path', 'like', $searchValue)
                                      ->orWhere('controller_method', 'like', $searchValue);
                                });
                                break;
                            // Добавьте другие поля для фильтрации по мере необходимости
                        }
                    }
                }
            }
        }

        // Пункт 16.a: sortBy – Массив с правилами сортировки списка.
        if ($request->has('sortBy')) {
            $sorts = json_decode($request->input('sortBy'), true);
            if (is_array($sorts)) {
                foreach ($sorts as $sort) {
                    if (isset($sort['key']) && isset($sort['order']) && in_array($sort['order'], ['asc', 'desc'])) {
                        // Убедимся, что поле для сортировки существует в таблице, чтобы избежать SQL инъекций
                        // Список разрешенных полей для сортировки
                        $allowedSortKeys = [
                            'id', 'full_url', 'http_method', 'controller_path',
                            'controller_method', 'user_id', 'ip_address',
                            'response_status', 'requested_at', 'created_at'
                        ];
                        if (in_array($sort['key'], $allowedSortKeys)) {
                            $query->orderBy($sort['key'], $sort['order']);
                        }
                    }
                }
            }
        } else {
            // Сортировка по умолчанию: по убыванию времени запроса
            $query->orderBy('requested_at', 'desc');
        }

        // Пункт 15: Метод получение списка возвращает элементы постранично.
        // Пункт 16.d: count – Количество записей, которое должно вернуться на странице
        $perPage = $request->input('count', 10); // По умолчанию 10 элементов на страницу
        $logs = $query->paginate($perPage, ['*'], 'page', $request->input('page', 1));

        // Пункт 13: Метод получения списка логов возвращает сокращенную информацию:
        // Адрес вызванного метода (full_url)
        // Класс и его метод, обработавшие вызов (controller_path, controller_method)
        // Код статуса ответа (response_status)
        // Время вызова (requested_at)

        // Для постраничного вывода DTO
        $transformedLogs = $logs->getCollection()->map(function ($log) {
            return [
                'id' => $log->id,
                'full_url' => $log->full_url,
                'http_method' => $log->http_method,
                'controller' => $log->controller_path,
                'method' => $log->controller_method,
                'response_status' => $log->response_status,
                'requested_at' => $log->requested_at->toDateTimeString(), // Форматируем дату для удобства
            ];
        });

        return response()->json([
            'data' => $transformedLogs,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'from' => $logs->firstItem(),
                'last_page' => $logs->lastPage(),
                'path' => $logs->path(),
                'per_page' => $logs->perPage(),
                'to' => $logs->lastItem(),
                'total' => $logs->total(),
            ],
            'links' => [
                'first' => $logs->url(1),
                'last' => $logs->url($logs->lastPage()),
                'prev' => $logs->previousPageUrl(),
                'next' => $logs->nextPageUrl(),
            ],
        ], 200);
    }

    /**
     * Получить лог запроса по идентификатору.
     * (Пункт 12, 14)
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        // Пункт 6.b: Администратор приложения может получить информацию по логам.
        if (!Auth::check() || !Auth::user()->hasPermission('view-request-logs')) {
            return response()->json(['message' => 'Недостаточно прав. Необходимо разрешение "view-request-logs".'], 403);
        }
        
        $logRequest = LogRequest::find($id);

        if (!$logRequest) {
            throw new ModelNotFoundException("Log request not found.");
        }

        // Пункт 14: Метод получения лога по идентификатору возвращает полный экземпляр записи
        return response()->json(LogRequestResourceDTO::fromModel($logRequest)->toArray(), 200);
    }
}
