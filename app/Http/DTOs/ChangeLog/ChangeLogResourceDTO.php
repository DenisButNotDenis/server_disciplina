<?php

namespace App\Http\DTOs\ChangeLog;

use Spatie\LaravelData\Data;
use Carbon\Carbon;
use App\Models\ChangeLog; // Импортируем модель ChangeLog

/**
 * Класс DTO для представления одной записи лога изменений.
 * (Пункт 9, 20)
 */
final readonly class ChangeLogResourceDTO extends Data
{
    public function __construct(
        public int $id,
        public string $mutatableType, // Тип мутировавшей модели (например, 'App\Models\User')
        public int $mutatableId,     // ID мутировавшей записи
        public ?array $oldValues,    // Значение до мутации (только изменившиеся свойства)
        public ?array $newValue,     // Значение после мутации (только изменившиеся свойства)
        public ?int $userId,         // ID пользователя, совершившего изменение (nullable)
        public string $event,        // Тип события (created, updated, deleted, restored)
        public Carbon $createdAt,
    ) {}

    /**
     * Создает экземпляр DTO из объекта модели ChangeLog.
     * (Пункт 20 - Методы запроса истории изменений при возврате коллекции пользователю,
     * содержат в элементах [значение элементов до/после мутации] только изменившиеся свойства.)
     *
     * @param ChangeLog $changeLog Модель ChangeLog.
     * @return self
     */
    public static function fromModel(ChangeLog $changeLog): self
    {
        $oldValuesDiff = null;
        $newValuesDiff = null;

        // Логика для получения только изменившихся свойств
        if ($changeLog->event === 'updated') {
            $oldValuesDiff = [];
            $newValuesDiff = [];
            foreach ($changeLog->new_values as $key => $value) {
                // Если ключ был изменен (существовал и изменился)
                if (array_key_exists($key, $changeLog->old_values) && $changeLog->old_values[$key] !== $value) {
                    $oldValuesDiff[$key] = $changeLog->old_values[$key];
                    $newValuesDiff[$key] = $value;
                }
                // Если ключ новый (был добавлен в new_values, но не было в old_values)
                // Это тоже считается изменением
                elseif (!array_key_exists($key, $changeLog->old_values)) {
                    $newValuesDiff[$key] = $value;
                }
            }
            // Также проверяем поля, которые были удалены (были в old_values, но нет в new_values)
            foreach ($changeLog->old_values as $key => $value) {
                if (!array_key_exists($key, $changeLog->new_values)) {
                    $oldValuesDiff[$key] = $value;
                }
            }
        } else {
            // Для created и deleted/restored, возвращаем полные значения, так как все "изменилось" или было добавлено/удалено
            $oldValuesDiff = $changeLog->old_values;
            $newValuesDiff = $changeLog->new_values;
        }

        return new self(
            id: $changeLog->id,
            mutatableType: $changeLog->mutatable_type,
            mutatableId: $changeLog->mutatable_id,
            oldValues: $oldValuesDiff, // Передаем только измененные старые значения
            newValue: $newValuesDiff, // Передаем только измененные новые значения
            userId: $changeLog->user_id,
            event: $changeLog->event,
            createdAt: $changeLog->created_at,
        );
    }
}