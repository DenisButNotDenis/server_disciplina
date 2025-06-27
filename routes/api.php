<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\UserRoleController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ChangeLogController;
use App\Http\Controllers\GitWebhookController;
use App\Http\Controllers\LogRequestController;
use App\Http\Controllers\ReportController; // Импортируем новый контроллер для отчетов
use App\Http\Middleware\AuthenticateApiToken;

Route::get('/test', function () {
    return response()->json(['message' => 'Test API route works!'], 200);
});

// Группа роутов для авторизации и регистрации
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);

    // Маршруты для 2FA
    Route::post('2fa/request-code', [AuthController::class, 'requestTwoFactorCode']);
    Route::post('2fa/verify-code', [AuthController::class, 'verifyTwoFactorCode']);

    Route::middleware([AuthenticateApiToken::class])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('out', [AuthController::class, 'logout']);
        Route::get('tokens', [AuthController::class, 'tokens']);
        Route::post('out_all', [AuthController::class, 'logoutAll']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('change_password', [AuthController::class, 'changePassword']);

        // Маршрут для включения/отключения 2FA
        Route::post('2fa/toggle', [AuthController::class, 'toggleTwoFactorAuth']);
    });
});

// Группа роутов для управления ролями
Route::prefix('roles')->middleware([AuthenticateApiToken::class])->group(function () {
    Route::get('/', [RoleController::class, 'index']);
    Route::get('/{role}', [RoleController::class, 'show']);
    Route::post('/', [RoleController::class, 'store']);
    Route::put('/{role}', [RoleController::class, 'update']);
    Route::delete('/{role}', [RoleController::class, 'destroy']);
    Route::post('/{role}/restore', [RoleController::class, 'restore']);

    // Связи Роль-Разрешение
    Route::post('{role}/permissions', [RolePermissionController::class, 'attachPermission']);
    Route::delete('{role}/permissions/{permission}', [RolePermissionController::class, 'detachPermission']);

    // Маршрут для получения истории изменений конкретной роли
    Route::get('{role}/history', [ChangeLogController::class, 'showRoleHistory']);
});

// Группа роутов для управления разрешениями
Route::prefix('permissions')->middleware([AuthenticateApiToken::class])->group(function () {
    Route::get('/', [PermissionController::class, 'index']);
    Route::get('/{permission}', [PermissionController::class, 'show']);
    Route::post('/', [PermissionController::class, 'store']);
    Route::put('/{permission}', [PermissionController::class, 'update']);
    Route::delete('/{permission}', [PermissionController::class, 'destroy']);
    Route::post('/{permission}/restore', [PermissionController::class, 'restore']);

    // Маршрут для получения истории изменений конкретного разрешения
    Route::get('{permission}/history', [ChangeLogController::class, 'showPermissionHistory']);
});

// Группа роутов для управления связями Пользователь-Роль
Route::prefix('users/{user}')->middleware([AuthenticateApiToken::class])->group(function () {
    Route::post('roles', [UserRoleController::class, 'attachRole']);
    Route::delete('roles/{role}', [UserRoleController::class, 'detachRole']);
});

// Группа роутов для управления пользователями
Route::prefix('users')->middleware([AuthenticateApiToken::class])->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/{user}', [UserController::class, 'show']);
    Route::put('/{user}', [UserController::class, 'update']);
    Route::delete('/{user}', [UserController::class, 'destroy']);
    Route::post('/{user}/restore', [UserController::class, 'restore']);

    // Маршрут для получения истории изменений конкретного пользователя
    Route::get('{user}/history', [ChangeLogController::class, 'showUserHistory']);
});

// Группа роутов для управления логами изменений
Route::prefix('change-logs')->middleware([AuthenticateApiToken::class])->group(function () {
    Route::get('/', [ChangeLogController::class, 'index']);
    Route::post('{changeLog}/revert', [ChangeLogController::class, 'revert']);
});

// Маршрут для Git Webhook
Route::post('/hooks/git', [GitWebhookController::class, 'handle']);

// Группа роутов для управления логами запросов
Route::prefix('request-logs')->middleware([AuthenticateApiToken::class])->group(function () {
    Route::get('/', [LogRequestController::class, 'index']);
    Route::get('/{id}', [LogRequestController::class, 'show']);
});

// Новая группа роутов для генерации отчетов
Route::prefix('reports')->middleware([AuthenticateApiToken::class])->group(function () {
    // Маршрут для запуска генерации отчета (Пункт 5)
    Route::post('generate', [ReportController::class, 'generateReport']);
});
