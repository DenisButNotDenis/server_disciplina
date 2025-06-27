<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Jobs\GenerateReportJob; // Импортируем нашу фоновую задачу
use App\Models\User; // Для получения email администраторов

/**
 * Контроллер для запуска фоновых задач по генерации отчетов.
 * (Пункт 5, 9)
 */
class ReportController extends Controller
{
    /**
     * Диспетчеризирует задачу по генерации отчета.
     * Отчет формируется в файл и отправляется администраторам.
     * (Пункт 5)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateReport(Request $request): JsonResponse
    {
        // Проверка разрешения: только администраторы могут генерировать отчеты
        // (Пункт 9 - отчет отправляется администраторам, подразумевается, что они его и запрашивают)
        if (!Auth::check() || !Auth::user()->hasPermission('generate-reports')) {
            return response()->json(['message' => 'Недостаточно прав. Необходимо разрешение "generate-reports".'], 403);
        }

        // Получаем email администраторов для "отправки" отчета (Пункт 9)
        // В реальном приложении это могут быть email'ы из конфига или БД.
        // Здесь для примера берем всех пользователей с ролью 'admin'.
        $adminEmails = User::whereHas('roles', function ($query) {
            $query->where('code', 'admin');
        })->pluck('email')->toArray();

        if (empty($adminEmails)) {
            return response()->json(['message' => 'Нет зарегистрированных администраторов для отправки отчета.'], 400);
        }

        // Тип отчета (может быть передан в запросе, для гибкости)
        $reportType = $request->input('report_type', 'Ежедневный отчет активности API'); // Пункт 8

        // Диспетчеризуем задачу в очередь
        GenerateReportJob::dispatch($reportType, $adminEmails);

        return response()->json(['message' => 'Задача по генерации отчета поставлена в очередь.'], 202);
    }
}
