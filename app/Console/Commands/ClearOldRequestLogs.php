?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LogRequest; // Импортируем модель LogRequest
use Carbon\Carbon; // Для работы с датами
use Illuminate\Support\Facades\Log; // Для логирования

class ClearOldRequestLogs extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'logs:clear-old-requests';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Clears request logs older than 73 hours.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $threshold = Carbon::now()->subHours(73); // Логи старше 73 часов (Пункт 6.c)

        $deletedCount = LogRequest::where('requested_at', '<', $threshold)->delete();

        if ($deletedCount > 0) {
            $this->info("Successfully deleted {$deletedCount} old request logs.");
            Log::info("Successfully deleted {$deletedCount} old request logs older than {$threshold->toDateTimeString()}.");
        } else {
            $this->info("No old request logs found to delete.");
            Log::info("No old request logs found to delete older than {$threshold->toDateTimeString()}.");
        }
    }
}
