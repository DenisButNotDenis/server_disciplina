<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log; // Для логирования
use Illuminate\Support\Facades\Cache; // Для управления блокировкой
use Symfony\Component\Process\Process; // Для выполнения команд оболочки
use Symfony\Component\Process\Exception\ProcessFailedException; // Для обработки ошибок выполнения команд
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException; // Для 400 Bad Request
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException; // Для 429 Too Many Requests

/**
 * Контроллер для обработки Git webhook.
 * Реализует автоматическое обновление кода на сервере.
 * (Пункт 9, 10, 11, 12)
 */
class GitWebhookController extends Controller
{
    private const LOCK_KEY = 'git_update_lock'; // Ключ для блокировки обновления

    /**
     * Обрабатывает запрос от Git webhook для обновления кода.
     * Маршрут открыт для вызова всем пользователям.
     * (Пункт 2, 3, 6, 7, 8, 9, 10, 11, 12)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        $secretKey = $request->input('secret_key');
        $configuredSecret = config('git_hooks.secret');

        // Пункт 7, 8: Валидация секретного ключа (чувствительна к регистру)
        if (empty($secretKey) || $secretKey !== $configuredSecret) {
            Log::warning("Неверный секретный ключ для webhook Git. IP: {$request->ip()}");
            throw new BadRequestHttpException('Invalid secret key.');
        }

        // Пункт 11, 12: Обеспечить возможность одновременного выполнения обновления только из одного потока.
        // Если маршрут вызывается в момент выполнения обновления кода,
        // пользователю отображается соответствующее сообщение.
        $lockAcquired = Cache::add(self::LOCK_KEY, true, config('git_hooks.update_lock_timeout'));

        if (!$lockAcquired) {
            Log::info("Попытка повторного обновления кода, когда процесс уже запущен. IP: {$request->ip()}");
            throw new TooManyRequestsHttpException(config('git_hooks.update_lock_timeout'), 'Код уже обновляется. Пожалуйста, попробуйте позже.');
        }

        try {
            Log::info("Начало обновления кода. Дата: " . now()->format('Y-m-d H:i:s') . ", IP: {$request->ip()}"); // Пункт 9.1

            $output = '';
            $errorOutput = '';
            $commands = [
                // Пункт 9.2: Переключение на главную ветку проекта (main)
                'git checkout ' . config('git_hooks.branch_to_pull'),
                // Пункт 9.3: Отмена всех изменений (если они присутствовали)
                'git reset --hard HEAD',
                // Пункт 9.4: Обновление проекта с гита до последней актуальной версии
                'git pull origin ' . config('git_hooks.branch_to_pull'),
                // Опционально: composer install (если зависимости могли измениться)
                // 'composer install --no-dev --prefer-dist',
                // Опционально: php artisan migrate (если есть новые миграции)
                // 'php artisan migrate --force', // --force нужен для продакшна
                // Опционально: php artisan cache:clear, php artisan config:clear и т.д.
                // 'php artisan cache:clear',
                // 'php artisan config:clear',
                // 'php artisan route:clear',
                // 'php artisan view:clear',
                // 'php artisan optimize:clear',
            ];

            foreach ($commands as $command) {
                Log::info("Выполнение команды: {$command}");
                $process = Process::fromShellCommandline($command, base_path()); // Выполняем в корне проекта
                $process->setTimeout(3600); // Таймаут в секундах (1 час), если команды очень долгие
                $process->run();

                if (!$process->isSuccessful()) {
                    $errorOutput .= "Ошибка выполнения команды '{$command}':\n" . $process->getErrorOutput() . "\n";
                    Log::error("Ошибка обновления Git: {$errorOutput}");
                    throw new ProcessFailedException($process);
                }
                $output .= "Результат команды '{$command}':\n" . $process->getOutput() . "\n";
                Log::info("Результат выполнения команды: {$command}"); // Логируем результат выполнения
            }

            Log::info("Обновление кода успешно завершено. IP: {$request->ip()}"); // Пункт 9.1
            // Пункт 10: Пользователю отображается соответствующее сообщение.
            return response()->json([
                'message' => 'Код успешно обновлен. Подробности в логах сервера.',
                'output' => $output
            ], 200);

        } catch (ProcessFailedException $exception) {
            Log::error("Сбой обновления Git: " . $exception->getMessage());
            return response()->json([
                'message' => 'Произошла ошибка при обновлении кода. Проверьте логи сервера.',
                'error' => $exception->getMessage(),
                'details' => $exception->getProcess()->getErrorOutput()
            ], 500);
        } catch (\Exception $e) {
            Log::error("Неожиданная ошибка при обновлении Git: " . $e->getMessage());
            return response()->json([
                'message' => 'Произошла непредвиденная ошибка. Проверьте логи сервера.',
                'error' => $e->getMessage()
            ], 500);
        } finally {
            Cache::forget(self::LOCK_KEY); // Снимаем блокировку независимо от результата
        }
    }
}
