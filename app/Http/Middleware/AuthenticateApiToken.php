<?php

namespace App\Http\Middleware;

use Closure; // Для продолжения обработки запроса
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Для аутентификации пользователя
use Illuminate\Support\Facades\Hash; // Для проверки хэша (хотя мы используем hash() для access-токена)
use Illuminate\Support\Str; // Для работы со строками
use App\Models\AccessToken; // Моя модель AccessToken
use Carbon\Carbon; // Для работы со временем (срок действия токена)
use Symfony\Component\HttpFoundation\Response; // Для возврата HTTP-ответа

class AuthenticateApiToken
{
    /**
     * Обрабатывает входящий запрос.
     *
     * @param Request $request Входящий HTTP-запрос.
     * @param Closure $next Функция, которая продолжает обработку запроса (передает его дальше).
     * @return Response Ответ, который будет отправлен клиенту.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Получаю заголовок 'Authorization' из запроса.
        // Клиенты обычно отправляют токен в формате "Bearer <токен>"
        $header = $request->header('Authorization');

        // 2. Проверяю, есть ли заголовок и начинается ли он с "Bearer ".
        if (!$header || !Str::startsWith($header, 'Bearer ')) {
            // Если нет или формат неверный, возвращаем ошибку 401 (Не авторизован)
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 3. Извлекаю сам токен, убирая "Bearer "
        $plainTextToken = Str::substr($header, 7);

        // 4. Хэширую полученный токен, чтобы сравнить его с хэшем в базе данных.
        // Я храню в БД только хэши токенов для безопасности.
        $hashedToken = hash('sha256', $plainTextToken);

        // 5. Ищю токен в нашей таблице `access_tokens`.
        // Проверяю, что токен существует, не просрочен и соответствует хэшу.
        $accessToken = AccessToken::where('token', $hashedToken)
                                ->where('expires_at', '>', Carbon::now()) // Токен должен быть действителен
                                ->first();

        // 6. Если токен не найден или к нему не привязан пользователь
        if (!$accessToken || !$accessToken->user) {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        // 7. Если токен валиден, "вхожу" пользователя в систему для этого запроса.
        // Это позволит использовать Auth::user() в контроллере.
        Auth::login($accessToken->user);

        // 8. Передаем запрос дальше по цепочке (в контроллер).
        return $next($request);
    }
}