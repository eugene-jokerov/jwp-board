<?php

namespace App\Modules\Task\Infrastructure\Persistence;

use App\Modules\History\Domain\Contracts\HistoryRecorder;
use App\Modules\History\Domain\HistoryEntry;
use App\Modules\History\Domain\ValueObjects\EntityType;
use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardType;

/**
 * Декоратор репозитория карточек, который ведёт журнал изменений.
 *
 * История пишется здесь, а не в хендлерах: любой путь изменения карточки
 * проходит через порт, поэтому «изменение без записи» невозможно. Декоратор
 * работает внутри транзакции команды (TransactionMiddleware), значит правка
 * карточки и записи журнала атомарны.
 *
 * На update пишется одна запись на каждое изменённое поле. Значения полей
 * пишутся строкой; для description — null, чтобы не таскать большой текст
 * в журнале (правило из требований к истории).
 */
final readonly class HistoryRecordingTaskRepository implements TaskRepository
{
    /**
     * Поля карточки, история которых ведётся, и способ сериализации значения.
     */
    private const array TRACKED_FIELDS = [
        'title',
        'slug',
        'description',
        'deadline',
        'type',
        'column',
        'priority',
        'idea',
        'direction_id',
        'initiative_id',
        'project_id',
    ];

    public function __construct(
        private TaskRepository $inner,
        private HistoryRecorder $history,
    ) {}

    public function findForUser(int $userId, int $id): Task
    {
        return $this->inner->findForUser($userId, $id);
    }

    public function listForUser(int $userId, array $filters = []): array
    {
        return $this->inner->listForUser($userId, $filters);
    }

    public function listInitialForUser(int $userId, array $filters, int $perLevelLimit): array
    {
        return $this->inner->listInitialForUser($userId, $filters, $perLevelLimit);
    }

    public function countForUser(int $userId, array $filters = []): int
    {
        return $this->inner->countForUser($userId, $filters);
    }

    public function countClosedByLevelForUser(int $userId, array $filters = []): array
    {
        return $this->inner->countClosedByLevelForUser($userId, $filters);
    }

    public function countByColumnForUser(int $userId, array $filters = []): array
    {
        return $this->inner->countByColumnForUser($userId, $filters);
    }

    public function countChildrenInProgress(Task $task): int
    {
        return $this->inner->countChildrenInProgress($task);
    }

    public function countChildrenInProgressFor(array $tasks): array
    {
        return $this->inner->countChildrenInProgressFor($tasks);
    }

    public function countChildren(Task $task): int
    {
        return $this->inner->countChildren($task);
    }

    public function findParent(int $userId, int $id, CardType $type): ?Task
    {
        return $this->inner->findParent($userId, $id, $type);
    }

    public function directionExists(int $userId, int $directionId): bool
    {
        return $this->inner->directionExists($userId, $directionId);
    }

    public function create(int $userId, array $attributes): Task
    {
        $task = $this->inner->create($userId, $attributes);

        $this->history->record(
            HistoryEntry::inserted(EntityType::Task, (int) $task->id, $userId),
        );

        return $task;
    }

    public function update(Task $task, array $changes): Task
    {
        if ($changes === []) {
            return $task;
        }

        $before = $this->snapshot($task);
        $updated = $this->inner->update($task, $changes);
        $after = $this->snapshot($updated);

        $entries = [];

        foreach (self::TRACKED_FIELDS as $field) {
            if (! array_key_exists($field, $changes)) {
                continue;
            }

            $entries[] = HistoryEntry::changed(
                EntityType::Task,
                (int) $updated->id,
                (int) $updated->user_id,
                $field,
                $field === 'description' ? null : $before[$field],
                $field === 'description' ? null : $after[$field],
            );
        }

        $this->history->recordMany($entries);

        return $updated;
    }

    public function delete(Task $task): void
    {
        // Запись делается до удаления: если DELETE упадёт, транзакция откатит журнал.
        $this->history->record(
            HistoryEntry::deleted(EntityType::Task, (int) $task->id, (int) $task->user_id),
        );

        $this->inner->delete($task);
    }

    /**
     * Строковый снимок отслеживаемых полей для old_value и new_value.
     *
     * Enum'ы приводятся к значениям, id — к строке, description не пишется,
     * дедлайн — строкой в формате базы (без ISO-суффикса пояса).
     *
     * @return array<string, string|null>
     */
    private function snapshot(Task $task): array
    {
        return [
            'title' => $task->title,
            'slug' => $task->slug,
            'description' => null,
            'deadline' => $task->deadline?->format('Y-m-d H:i:s'),
            'type' => $task->type->value,
            'column' => $task->column->value,
            'priority' => $task->priority->value,
            'idea' => $task->idea ? '1' : '0',
            'direction_id' => $task->direction_id === null ? null : (string) $task->direction_id,
            'initiative_id' => $task->initiative_id === null ? null : (string) $task->initiative_id,
            'project_id' => $task->project_id === null ? null : (string) $task->project_id,
        ];
    }
}
