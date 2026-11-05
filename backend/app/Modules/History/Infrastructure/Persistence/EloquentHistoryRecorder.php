<?php

namespace App\Modules\History\Infrastructure\Persistence;

use App\Modules\History\Domain\Contracts\HistoryRecorder;
use App\Modules\History\Domain\HistoryEntry;

/**
 * Eloquent-реализация журнала изменений.
 *
 * Пишет в рамках уже открытой транзакции команды: собственных транзакций
 * здесь нет намеренно, иначе запись могла бы сохраниться без самого изменения.
 * Время записи берётся из одного момента now() на пачку — записи одного
 * изменения выглядят одновременными.
 */
final class EloquentHistoryRecorder implements HistoryRecorder
{
    public function record(HistoryEntry $entry): void
    {
        $this->recordMany([$entry]);
    }

    public function recordMany(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $now = now();

        $rows = array_map(static fn (HistoryEntry $entry): array => [
            'entity_type' => $entry->entityType->value,
            'entity_id' => $entry->entityId,
            'user_id' => $entry->userId,
            'action' => $entry->action->value,
            'field' => $entry->field,
            'old_value' => $entry->oldValue,
            'new_value' => $entry->newValue,
            'created_at' => $now,
        ], $entries);

        HistoryRecord::query()->insert($rows);
    }
}
