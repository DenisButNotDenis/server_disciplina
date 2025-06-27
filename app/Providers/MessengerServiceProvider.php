<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Messenger\MessengerService;
use App\Services\Messenger\TelegramMessengerService;
use App\Models\Messenger;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service Provider для регистрации сервисов мессенджеров.
 * (Пункт 9)
 */
class MessengerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('messenger.telegram', function ($app) {
            return new TelegramMessengerService();
        });


        // Главный сервис-фасад для получения конкретного мессенджера по его имени
        $this->app->bind(MessengerService::class, function ($app, $parameters) {
            $messengerName = $parameters['name'] ?? null;

            if (!$messengerName) {
                throw new InvalidArgumentException('Messenger name is required to resolve MessengerService.');
            }

            // Получаем MessengerService из контейнера по его имени
            $service = $app->make("messenger.{$messengerName}");

            if (!$service instanceof MessengerService) {
                throw new RuntimeException("Messenger service for '{$messengerName}' not found or is not an instance of MessengerService.");
            }

            return $service;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
