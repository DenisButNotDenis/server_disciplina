<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
    use App\Models\User; 
    use App\Models\Role; 
    use App\Models\Permission; 
    use App\Observers\ChangeLogObserver; 
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Регистрация Observer'ов для моделей
        // (Пункт 5 - Логирование мутаций происходит автоматически)
        User::observe(ChangeLogObserver::class);
        Role::observe(ChangeLogObserver::class);
        Permission::observe(ChangeLogObserver::class);
    }
}
