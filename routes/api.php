<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController; // Импортирую мой контроллер
use App\Http\Middleware\AuthenticateApiToken; // Импортирую мой Middleware

Route::get('/test', function () {
    return response()->json(['message' => 'Test API route works!'], 200);
});
// Группа роутов для авторизации и регистрации с префиксом "auth"
Route::prefix('auth')->group(function () {

    // --- Открытые роуты (доступны всю, даже неавторизованным) ---
    // POST-запрос на /api/auth/login вызывает метод login в AuthController
    Route::post('login', [AuthController::class, 'login']);

    // POST-запрос на /api/auth/register вызывает метод register в AuthController
    // "Только неавторизованные" - это больше логическое требование,
    // но здесь нет прямого Middleware для этого. Обычно регистрация открыта.
    Route::post('register', [AuthController::class, 'register']);

    // --- Роуты, требующие авторизации (защищены моим Middleware) ---
    // Применяю Middleware AuthenticateApiToken ко всем роутам внутри этой группы.
    Route::middleware([AuthenticateApiToken::class])->group(function () {
        // GET-запрос на /api/auth/me вызывает метод me в AuthController
        Route::get('me', [AuthController::class, 'me']);

        // POST-запрос на /api/auth/out вызывает метод logout в AuthController
        Route::post('out', [AuthController::class, 'logout']);

        // GET-запрос на /api/auth/tokens вызывает метод tokens в AuthController
        Route::get('tokens', [AuthController::class, 'tokens']);

        // POST-запрос на /api/auth/out_all вызывает метод logoutAll в AuthController
        Route::post('out_all', [AuthController::class, 'logoutAll']);

        // POST-запрос на /api/auth/refresh вызывает метод refresh в AuthController
        // Этот роут НЕ требует Access Token в заголовке, он работает по Refresh Token в теле запроса.
        // Но я поместили его сюда, чтобы он попадал под общую группу auth,
        // а логика проверки refresh-токена находится в самом контроллере.
        Route::post('refresh', [AuthController::class, 'refresh']);

        // POST-запрос на /api/auth/change_password вызывает метод changePassword в AuthController
        Route::post('change_password', [AuthController::class, 'changePassword']);
    });
});