<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth; // Для получения ID пользователя
use Illuminate\Support\Facades\Route; // Для получения информации о маршруте/контроллере
use App\Models\LogRequest; // Импортируем модель для логирования запросов
use Illuminate\Support\Facades\Log; // Для отладочных логов Laravel
use Illuminate\Support\Str; // Для работы со строками

/**
 * Middleware для логирования всех входящих HTTP запросов и исходящих ответов.
 * (Пункт 6.a, 7)
 */
class RequestResponseLogger
{
    /**
     * Обрабатывает входящий запрос.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Исключаем логирование для маршрутов, не относящихся к API,
        // и для самого webhook обновления кода, чтобы избежать рекурсии/лишних логов.
        if (!Str::startsWith($request->path(), 'api/') || $request->path() === 'api/hooks/git') {
            return $next($request);
        }

        $logData = [
            'full_url' => $request->fullUrl(), // Пункт 7.a.i
            'http_method' => $request->method(), // Пункт 7.a.ii
            'request_body' => json_encode($request->all()), // Пункт 7.a.v (тело запроса)
            'request_headers' => json_encode($request->headers->all()), // Пункт 7.a.vi (заголовки запроса)
            'user_id' => Auth::id(), // Пункт 7.a.vii (будет null, если пользователь не авторизован)
            'ip_address' => $request->ip(), // Пункт 7.a.viii
            'user_agent' => $request->header('User-Agent'), // Пункт 7.a.ix
            'requested_at' => now(), // Пункт 7.a.xiii (время вызова метода)
        ];

        // Попытка получить информацию о контроллере и методе, если маршрут найден
        $currentRoute = Route::currentRouteAction(); // Возвращает 'App\Http\Controllers\MyController@myMethod'

        if ($currentRoute && is_string($currentRoute)) { // Убедимся, что это строка
            list($controller, $method) = explode('@', $currentRoute);
            $logData['controller_path'] = $controller; // Пункт 7.a.iii (путь до контроллера)
            $logData['controller_method'] = $method; // Пункт 7.a.iv (наименование метода контроллера)
        } else {
             // Если маршрут не удалось определить (например, 404 Not Found)
            $logData['controller_path'] = null;
            $logData['controller_method'] = null;
        }

        // Обработка запроса и получение ответа
        $response = $next($request);

        // Добавляем информацию об ответе
        $logData['response_status'] = $response->getStatusCode(); // Пункт 7.a.x (код статуса ответа)
        // Для тела ответа: если это JSON response, используем original, иначе content
        $logData['response_body'] = json_encode($response->original ?? $response->getContent()); // Пункт 7.a.xi
        $logData['response_headers'] = json_encode($response->headers->all()); // Пункт 7.a.xii (заголовки ответа)

        // Сохраняем лог в базу данных
        try {
            LogRequest::create($logData);
        } catch (\Exception $e) {
            // Логируем ошибку, если не удалось сохранить лог (не должно блокировать работу приложения)
            Log::error('Failed to log request: ' . $e->getMessage(), ['log_data' => $logData]);
        }

        return $response;
    }
}
