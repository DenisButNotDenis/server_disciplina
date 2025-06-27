<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\NotificationLog; // Импортируем модель логов уведомлений
use App\Http\DTOs\Report\NotificationReportDTO;
use App\Http\DTOs\Report\NotificationReportCollectionDTO;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Контроллер для генерации различных отчетов.
 * (Пункт 18, 19)
 */
class ReportController extends Controller
{
    /**
     * Генерирует отчет по логам уведомлений.
     * (Пункт 18, 19)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateNotificationReport(Request $request): JsonResponse
    {
        // Пункт 19: Проверка доступа к методу
        // Только пользователи с разрешением 'get-notification-report' могут генерировать этот отчет.
        if (!Auth::check() || !Auth::user()->hasPermission('get-notification-report')) {
            throw new AccessDeniedHttpException('Необходимое разрешение: get-notification-report');
        }

        // Получаем все логи уведомлений, загружая связанные модели User и Messenger
        // Пункт 18: Отчет включает данные из NotificationLog, User (username) и Messenger (name).
        $logs = NotificationLog::with(['user', 'messenger'])
                               ->orderBy('created_at', 'desc')
                               ->get();

        // Считаем статистику
        $totalLogs = $logs->count();
        $successfulSends = $logs->where('status', 'sent')->count();
        $failedSends = $logs->where('status', 'failed')->count();
        $skippedSends = $logs->where('status', 'skipped')->count();
        $retryingSends = $logs->where('status', 'retrying')->count();


        // Преобразуем коллекцию моделей в коллекцию DTO для отчета
        $logCollectionDTO = NotificationReportCollectionDTO::collect($logs);

        // Создаем и возвращаем финальный DTO отчета
        $reportDTO = new NotificationReportDTO(
            logs: $logCollectionDTO,
            totalLogs: $totalLogs,
            successfulSends: $successfulSends,
            failedSends: $failedSends,
            skippedSends: $skippedSends,
            retryingSends: $retryingSends,
        );

        return response()->json($reportDTO->toArray(), 200);
    }

}
