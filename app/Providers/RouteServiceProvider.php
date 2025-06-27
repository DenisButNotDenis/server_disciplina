<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter; 
use Illuminate\Support\Facades\Route; 

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            // Маршруты API
            Route::middleware('api') // Применяет middleware 'api' (из Http/Kernel.php)
                ->prefix('api')    // Добавляет префикс '/api' ко всем маршрутам в этом файле
                ->group(base_path('routes/api.php')); // Указывает, какой файл маршрутов загружать

            // Маршруты веб-приложения
            Route::middleware('web') // Применяет middleware 'web'
                ->group(base_path('routes/web.php')); // Указывает, какой файл маршрутов загружать
        });
    }
}