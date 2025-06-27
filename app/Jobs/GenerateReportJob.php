<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log; // Для логирования
use Illuminate\Support\Facades\Storage; // Для работы с файлами
use Carbon\Carbon; // Для работы с датами и временными интервалами
use App\Models\LogRequest; // Для рейтинга вызываемых методов
use App\Models\ChangeLog; // Для рейтинга редактируемых сущностей
use App\Models\User; // Для рейтинга пользователей
use App\Http\DTOs\Report\MethodCallReportDTO;
use App\Http\DTOs\Report\EntityModificationReportDTO;
use App\Http\DTOs\Report\UserActivityReportDTO;
use App\Http\DTOs\Report\FullReportDTO;
use Illuminate\Support\Collection; // Для подсказки типов

// Дополнительные трейты для управления таймаутами и повторениями
use Illuminate\Contracts\Queue\ShouldBeUnique; // Если задача должна быть уникальной
use Illuminate\Queue\Middleware\RateLimited; // Для ограничения частоты, если нужно
use Illuminate\Support\Str; // Для форматирования имен файлов
use Illuminate\Queue\Attributes\WithTimeout; // Для установки таймаута выполнения
use Illuminate\Queue\Attributes\WithCappedAttempts; // Для ограничения количества попыток

#[WithTimeout(config: 'reports.max_execution_minutes')] // Пункт 11: Максимальный срок выполнения задачи
#[WithCappedAttempts(attempts: config('reports.job_retries.max_attempts'), decayMinutes: config('reports.job_retries.timeout_minutes'))] // Пункт 12, 13
class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $reportType; // Тип отчета (например, 'Activity Summary')
    public array $adminEmails; // Список email администраторов для "отправки" отчета

    /**
     * Конструктор новой задачи.
     *
     * @param string $reportType Тип от отчета.
     * @param array $adminEmails Email-адреса администраторов для "отправки" отчета.
     */
    public function __construct(string $reportType, array $adminEmails)
    {
        $this->reportType = $reportType;
        $this->adminEmails = $adminEmails;
        // Пункт 15: Логирование начала работы задачи.
        Log::info("Job [{$this->job->getJobId()}] 'GenerateReportJob' initialized for report type '{$this->reportType}'.");
    }

    /**
     * Выполняет фоновую задачу.
     */
    public function handle(): void
    {
        Log::info("Job [{$this->job->getJobId()}] 'GenerateReportJob' started handling. Current attempt: {$this->attempts()}."); // Пункт 15

        // Устанавливаем интервал данных для отчета (Пункт 10)
        $dataIntervalHours = config('reports.data_interval_hours');
        $dataPeriodEnd = Carbon::now();
        $dataPeriodStart = $dataPeriodEnd->copy()->subHours($dataIntervalHours);

        $reportFileName = '';
        try {
            // Создаем директорию для отчетов, если её нет
            if (!Storage::exists(config('reports.report_path'))) {
                Storage::makeDirectory(config('reports.report_path'));
            }

            // 5.a. Рейтинг вызываемых методов
            $methodCallRanking = $this->getMethodCallRanking($dataPeriodStart, $dataPeriodEnd);
            Log::info("Collected method call ranking data.");

            // 5.b. Рейтинг редактируемых сущностей
            $entityModificationRanking = $this->getEntityModificationRanking($dataPeriodStart, $dataPeriodEnd);
            Log::info("Collected entity modification ranking data.");

            // 5.c. Рейтинг пользователей
            $userActivityRanking = $this->getUserActivityRanking($dataPeriodStart, $dataPeriodEnd);
            Log::info("Collected user activity ranking data.");

            // Пункт 8: Файл отчета содержит информацию о типе отчета, и времени актуальности данных.
            $fullReportDTO = new FullReportDTO(
                reportType: $this->reportType,
                reportGeneratedAt: Carbon::now(),
                dataPeriodStart: $dataPeriodStart,
                dataPeriodEnd: $dataPeriodEnd,
                methodCallRanking: MethodCallReportDTO::collection($methodCallRanking),
                entityModificationRanking: EntityModificationReportDTO::collection($entityModificationRanking),
                userActivityRanking: UserActivityReportDTO::collection($userActivityRanking),
            );

            // Пункт 7: Отчет формируется в файл
            $reportFileName = $this->generateReportFile($fullReportDTO);
            Log::info("Report file '{$reportFileName}' generated successfully.");

            // Пункт 9, 14: Файл отчета отправляется администраторам приложения (логируем)
            Log::info("Report '{$reportFileName}' sent to administrators: " . implode(', ', $this->adminEmails) . " (Simulated sending).");

        } catch (\Exception $e) {
            Log::error("Job [{$this->job->getJobId()}] 'GenerateReportJob' failed: " . $e->getMessage());
            // Если возникла ошибка, можно пометить задачу как проваленную
            $this->fail($e);
        } finally {
            // Пункт 9: После отправки отчет удаляется с сервера.
            if ($reportFileName && Storage::exists($reportFileName)) {
                Storage::delete($reportFileName);
                Log::info("Report file '{$reportFileName}' deleted from server.");
            }
            // Пункт 15: Логирование завершения работы задачи.
            Log::info("Job [{$this->job->getJobId()}] 'GenerateReportJob' finished handling.");
        }
    }

    /**
     * Собирает данные для рейтинга вызываемых методов.
     * (Пункт 5.a, 6)
     *
     * @param Carbon $start
     * @param Carbon $end
     * @return array
     */
    protected function getMethodCallRanking(Carbon $start, Carbon $end): array
    {
        $ranking = LogRequest::selectRaw('
                COALESCE(CONCAT(controller_path, \'@\', controller_method), full_url) as method,
                COUNT(*) as call_count,
                MAX(requested_at) as last_called_at
            ')
            ->whereBetween('requested_at', [$start, $end])
            ->whereNotNull('controller_path') // Исключаем методы, где контроллер не определен (например, 404)
            ->groupBy('method')
            ->orderByDesc('call_count')
            ->get();

        return $ranking->map(fn ($item) => new MethodCallReportDTO(
            method: $item->method,
            callCount: $item->call_count,
            lastCalledAt: $item->last_called_at ? Carbon::parse($item->last_called_at) : null
        ))->toArray();
    }

    /**
     * Собирает данные для рейтинга редактируемых сущностей.
     * (Пункт 5.b, 6)
     *
     * @param Carbon $start
     * @param Carbon $end
     * @return array
     */
    protected function getEntityModificationRanking(Carbon $start, Carbon $end): array
    {
        $ranking = ChangeLog::selectRaw('
                mutatable_type as entity,
                COUNT(*) as modification_count,
                MAX(created_at) as last_modified_at
            ')
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('event', ['created', 'updated', 'restored']) // Только изменения, без удаления
            ->groupBy('mutatable_type')
            ->orderByDesc('modification_count')
            ->get();

        return $ranking->map(fn ($item) => new EntityModificationReportDTO(
            entity: $item->entity,
            modificationCount: $item->modification_count,
            lastModifiedAt: $item->last_modified_at ? Carbon::parse($item->last_modified_at) : null
        ))->toArray();
    }

    /**
     * Собирает данные для рейтинга пользователей.
     * (Пункт 5.c, 6)
     *
     * @param Carbon $start
     * @param Carbon $end
     * @return array
     */
    protected function getUserActivityRanking(Carbon $start, Carbon $end): array
    {
        $users = User::with('roles.permissions')->get(); // Загружаем роли и разрешения для подсчета

        $userActivity = [];

        foreach ($users as $user) {
            // Подсчет запросов
            $requestCount = LogRequest::where('user_id', $user->id)
                                        ->whereBetween('requested_at', [$start, $end])
                                        ->count();
            $lastRequestAt = LogRequest::where('user_id', $user->id)
                                        ->whereBetween('requested_at', [$start, $end])
                                        ->max('requested_at');

            // Подсчет изменений
            $modificationCount = ChangeLog::where('user_id', $user->id)
                                            ->whereBetween('created_at', [$start, $end])
                                            ->whereIn('event', ['created', 'updated', 'restored'])
                                            ->count();
            $lastModificationAt = ChangeLog::where('user_id', $user->id)
                                            ->whereBetween('created_at', [$start, $end])
                                            ->whereIn('event', ['created', 'updated', 'restored'])
                                            ->max('created_at');

            // Подсчет разрешений (количество разрешений, связанных через роли)
            $permissionCount = $user->roles->flatMap(fn ($role) => $role->permissions)->unique('id')->count();

            // Дата последней авторизации (т.е. последнего успешного входа).
            // Можно использовать AccessToken'ы, если там есть created_at или last_used_at
            $lastAuthorizationAt = $user->accessTokens()->whereBetween('created_at', [$start, $end])->max('created_at');


            $userActivity[] = new UserActivityReportDTO(
                userId: $user->id,
                username: $user->username,
                requestCount: $requestCount,
                lastRequestAt: $lastRequestAt ? Carbon::parse($lastRequestAt) : null,
                modificationCount: $modificationCount,
                lastModificationAt: $lastModificationAt ? Carbon::parse($lastModificationAt) : null,
                permissionCount: $permissionCount,
                lastAuthorizationAt: $lastAuthorizationAt ? Carbon::parse($lastAuthorizationAt) : null,
            );
        }

        // Сортировка по общему количеству активности или другим критериям
        usort($userActivity, function($a, $b) {
            return ($b->requestCount + $b->modificationCount) <=> ($a->requestCount + $a->modificationCount);
        });

        return $userActivity;
    }

    /**
     * Генерирует файл отчета на основе DTO.
     * (Пункт 7)
     *
     * @param FullReportDTO $reportData
     * @return string Имя файла (путь относительно storage/app)
     */
    protected function generateReportFile(FullReportDTO $reportData): string
    {
        $format = config('reports.report_format', 'json');
        $fileName = 'report_' . Str::slug($reportData->reportType) . '_' . Carbon::now()->format('Ymd_His') . '.' . $format;
        $filePath = config('reports.report_path') . '/' . $fileName;

        $content = '';
        switch ($format) {
            case 'json':
                // Включаем DTO в массив для более удобного json_encode
                $content = json_encode($reportData->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
            case 'html':
                // Простое HTML-представление для примера
                $content = "<h1>Отчет: {$reportData->reportType}</h1>";
                $content .= "<p>Сгенерировано: {$reportData->reportGeneratedAt->toDateTimeString()}</p>";
                $content .= "<p>Период данных: {$reportData->dataPeriodStart->toDateTimeString()} - {$reportData->dataPeriodEnd->toDateTimeString()}</p>";
                $content .= "<h2>Рейтинг вызываемых методов</h2>";
                $content .= "<ul>";
                foreach ($reportData->methodCallRanking as $item) {
                    $content .= "<li>{$item->method}: {$item->callCount} вызовов (Последний: {$item->lastCalledAt?->toDateTimeString()})</li>";
                }
                $content .= "</ul>";
                $content .= "<h2>Рейтинг редактируемых сущностей</h2>";
                $content .= "<ul>";
                foreach ($reportData->entityModificationRanking as $item) {
                    $content .= "<li>{$item->entity}: {$item->modificationCount} изменений (Последнее: {$item->lastModifiedAt?->toDateTimeString()})</li>";
                }
                $content .= "</ul>";
                $content .= "<h2>Рейтинг пользователей</h2>";
                $content .= "<ul>";
                foreach ($reportData->userActivityRanking as $item) {
                    $content .= "<li>{$item->username} (ID: {$item->userId}): Запросов: {$item->requestCount}, Изменений: {$item->modificationCount}, Разрешений: {$item->permissionCount}";
                    if ($item->lastRequestAt) $content .= ", Последний запрос: {$item->lastRequestAt->toDateTimeString()}";
                    if ($item->lastModificationAt) $content .= ", Последнее изменение: {$item->lastModificationAt->toDateTimeString()}";
                    if ($item->lastAuthorizationAt) $content .= ", Последняя авторизация: {$item->lastAuthorizationAt->toDateTimeString()}";
                    $content .= "</li>";
                }
                $content .= "</ul>";
                break;
            case 'csv':
                // Базовое CSV. Для сложных CSV лучше использовать библиотеку.
                $csvData = [];
                $csvData[] = ['Report Type', $reportData->reportType];
                $csvData[] = ['Generated At', $reportData->reportGeneratedAt->toDateTimeString()];
                $csvData[] = ['Data Period Start', $reportData->dataPeriodStart->toDateTimeString()];
                $csvData[] = ['Data Period End', $reportData->dataPeriodEnd->toDateTimeString()];
                $csvData[] = []; // Пустая строка для разделения
                $csvData[] = ['Method Call Ranking'];
                $csvData[] = ['Method', 'Call Count', 'Last Called At'];
                foreach ($reportData->methodCallRanking as $item) {
                    $csvData[] = [$item->method, $item->callCount, $item->lastCalledAt?->toDateTimeString()];
                }
                $csvData[] = [];
                $csvData[] = ['Entity Modification Ranking'];
                $csvData[] = ['Entity', 'Modification Count', 'Last Modified At'];
                foreach ($reportData->entityModificationRanking as $item) {
                    $csvData[] = [$item->entity, $item->modificationCount, $item->lastModifiedAt?->toDateTimeString()];
                }
                $csvData[] = [];
                $csvData[] = ['User Activity Ranking'];
                $csvData[] = ['User ID', 'Username', 'Request Count', 'Last Request At', 'Modification Count', 'Last Modification At', 'Permission Count', 'Last Authorization At'];
                foreach ($reportData->userActivityRanking as $item) {
                    $csvData[] = [
                        $item->userId, $item->username, $item->requestCount,
                        $item->lastRequestAt?->toDateTimeString(), $item->modificationCount,
                        $item->lastModificationAt?->toDateTimeString(), $item->permissionCount,
                        $item->lastAuthorizationAt?->toDateTimeString()
                    ];
                }

                // Преобразуем массив в CSV-строки
                $stream = fopen('php://temp', 'r+');
                foreach ($csvData as $row) {
                    fputcsv($stream, $row);
                }
                rewind($stream);
                $content = stream_get_contents($stream);
                fclose($stream);
                break;
            default:
                // По умолчанию или для txt
                $content = json_encode($reportData->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
        }

        Storage::put($filePath, $content);
        return $filePath; // Возвращаем путь относительно storage/app
    }
}
