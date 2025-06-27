<?php

namespace App\Http\DTOs\Report;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Carbon\Carbon;

/**
 * DTO для всего полного отчета.
 * (Пункт 8)
 */
final readonly class FullReportDTO extends Data
{
    public function __construct(
        public string $reportType,                                   // Тип отчета (например, "Ежедневный отчет активности API") (Пункт 8)
        public Carbon $reportGeneratedAt,                            // Время генерации отчета
        public Carbon $dataPeriodStart,                              // Начало временного интервала данных
        public Carbon $dataPeriodEnd,                                // Конец временного интервала данных
        public DataCollection $methodCallRanking,                    // Рейтинг вызываемых методов (Пункт 5.a)
        public DataCollection $entityModificationRanking,            // Рейтинг редактируемых сущностей (Пункт 5.b)
        public DataCollection $userActivityRanking,                  // Рейтинг пользователей (Пункт 5.c)
    ) {}
}
