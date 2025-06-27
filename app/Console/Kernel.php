<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\ClearOldRequestLogs; // Импортируем вашу команду

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // Ваша команда будет автоматически обнаружена, если вы используете $this->load(__DIR__.'/Commands');
        // Если нет, раскомментируйте или добавьте ее сюда:
        // ClearOldRequestLogs::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Планируем команду 'logs:clear-old-requests' на ежедневное выполнение.
        // (Пункт 6.c - автоматическая очистка)
        $schedule->command('logs:clear-old-requests')->daily(); // Выполняется каждый день в полночь
        // Если вы хотите указать точное время, например, 01:00 ночи:
        // $schedule->command('logs:clear-old-requests')->dailyAt('01:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}