<?php

namespace App\Observers;

use App\Models\ChangeLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth; // Для получения ID текущего пользователя
use Illuminate\Support\Facades\Log; // Для отладки, если нужно

/**
 * Наблюдатель (Observer) для логирования мутаций сущностей.
 * Слушает события created, updated, deleted, restored для моделей,
 * которые используют этот Observer.
 * (Пункт 5, 6, 14)
 */
class ChangeLogObserver
{
    /**
     * Создает запись лога для события "создание".
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function created(Model $model): void
    {
        // Не логируем создание самого лога изменений, чтобы избежать бесконечного цикла
        if ($model instanceof ChangeLog) {
            return;
        }

        try {
            ChangeLog::create([
                'mutatable_type' => $model::class, // Полное имя класса модели (например, 'App\Models\User')
                'mutatable_id' => $model->id,     // ID созданной записи
                'old_values' => null,             // Для создания старых значений нет
                'new_values' => $model->toArray(), // Все атрибуты новой записи
                'user_id' => Auth::id(),          // ID пользователя, который создал запись (может быть null)
                'event' => 'created',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log creation for model ' . $model::class . ': ' . $e->getMessage());
        }
    }

    /**
     * Создает запись лога для события "обновление".
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function updated(Model $model): void
    {
        // Не логируем обновление самого лога изменений
        if ($model instanceof ChangeLog) {
            return;
        }

        // Получаем только измененные атрибуты
        $changedAttributes = $model->getChanges();

        // Исключаем системные поля, которые обновляются при любом изменении
        unset($changedAttributes['updated_at']);
        unset($changedAttributes['remember_token']); // Если есть

        // Если реально ничего полезного не изменилось (только updated_at), не создаем запись
        if (empty($changedAttributes)) {
            return;
        }

        try {
            ChangeLog::create([
                'mutatable_type' => $model::class,
                'mutatable_id' => $model->id,
                'old_values' => $model->getOriginal(), // Все оригинальные атрибуты до изменения
                'new_values' => $model->toArray(),     // Все текущие атрибуты после изменения
                'user_id' => Auth::id(),
                'event' => 'updated',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log update for model ' . $model::class . ': ' . $e->getMessage());
        }
    }

    /**
     * Создает запись лога для события "удаление" (мягкое удаление).
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function deleted(Model $model): void
    {
        // Не логируем удаление самого лога изменений
        if ($model instanceof ChangeLog) {
            return;
        }

        try {
            ChangeLog::create([
                'mutatable_type' => $model::class,
                'mutatable_id' => $model->id,
                'old_values' => $model->getOriginal(), // Атрибуты записи до удаления
                'new_values' => null, // После удаления нет новых значений
                'user_id' => Auth::id(),
                'event' => 'deleted',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log deletion for model ' . $model::class . ': ' . $e->getMessage());
        }
    }

    /**
     * Создает запись лога для события "восстановление" (из мягкого удаления).
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function restored(Model $model): void
    {
        // Не логируем восстановление самого лога изменений
        if ($model instanceof ChangeLog) {
            return;
        }

        try {
            ChangeLog::create([
                'mutatable_type' => $model::class,
                'mutatable_id' => $model->id,
                'old_values' => $model->getOriginal(), // Атрибуты записи до восстановления
                'new_values' => $model->toArray(),     // Все атрибуты после восстановления
                'user_id' => Auth::id(),
                'event' => 'restored',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log restoration for model ' . $model::class . ': ' . $e->getMessage());
        }
    }
}
