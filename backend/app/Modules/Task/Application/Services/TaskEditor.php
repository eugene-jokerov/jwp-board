<?php

namespace App\Modules\Task\Application\Services;

use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Domain\Exceptions\ColumnChangeBlocked;
use App\Modules\Task\Domain\Exceptions\InvalidHierarchy;
use App\Modules\Task\Domain\Services\ColumnTransitionPolicy;
use App\Modules\Task\Domain\Services\TaskPlacement;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;

/**
 * Прикладной сервис изменения карточки.
 *
 * Сводит в один сценарий независимые изменения: заголовок, описание, ссылки
 * иерархии и колонку. Сначала проверяются доменные правила (иерархия и переход
 * между колонками), и только затем данные пишутся в хранилище: при нарушении
 * транзакция команды откатывает всё.
 */
final readonly class TaskEditor
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskPlacement $placement,
        private ColumnTransitionPolicy $policy,
    ) {}

    /**
     * Изменить карточку и вернуть её актуальное состояние.
     *
     * @param  array{direction_id?: int|null, initiative_id?: int|null, project_id?: int|null}  $links  переданные ссылки иерархии
     * @param  array{title?: string|null, description?: string|null, column?: CardColumn|null}  $fields
     *
     * @throws InvalidHierarchy если набор ссылок недопустим для типа карточки
     * @throws ColumnChangeBlocked если смену колонки блокируют дети в работе
     */
    public function update(Task $task, array $links, array $fields): Task
    {
        $changes = [];

        $resolved = $this->placement->resolveForUpdate($task, $this->mergeLinks($task, $links));

        foreach ($resolved as $field => $value) {
            if ($task->{$field} !== $value) {
                $changes[$field] = $value;
            }
        }

        $column = $fields['column'] ?? null;

        if ($column instanceof CardColumn) {
            $this->policy->ensureCanMove($task, $column);

            if ($task->column !== $column) {
                $changes['column'] = $column->value;
            }
        }

        foreach (['title', 'description'] as $field) {
            if (array_key_exists($field, $fields) && $task->{$field} !== $fields[$field]) {
                $changes[$field] = $fields[$field];
            }
        }

        return $this->tasks->update($task, $changes);
    }

    /**
     * Ссылки после обновления: переданные в запросе, остальные — текущие.
     *
     * @param  array{direction_id?: int|null, initiative_id?: int|null, project_id?: int|null}  $links
     * @return array{direction_id: int|null, initiative_id: int|null, project_id: int|null}
     */
    private function mergeLinks(Task $task, array $links): array
    {
        return [
            'direction_id' => array_key_exists('direction_id', $links) ? $links['direction_id'] : $task->direction_id,
            'initiative_id' => array_key_exists('initiative_id', $links) ? $links['initiative_id'] : $task->initiative_id,
            'project_id' => array_key_exists('project_id', $links) ? $links['project_id'] : $task->project_id,
        ];
    }
}
