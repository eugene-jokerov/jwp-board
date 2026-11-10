<?php

namespace App\Modules\Task\Application\Services;

use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Domain\Exceptions\ColumnChangeBlocked;
use App\Modules\Task\Domain\Exceptions\InvalidHierarchy;
use App\Modules\Task\Domain\Services\ColumnTransitionPolicy;
use App\Modules\Task\Domain\Services\TaskPlacement;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardPriority;

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
     * @param  array{title?: string|null, description?: string|null, slug?: string|null, deadline?: string|null, column?: CardColumn|null, priority?: CardPriority|null, idea?: bool}  $fields
     *
     * @throws InvalidHierarchy если набор ссылок недопустим для типа карточки
     *                          или у карточки с детьми меняется родитель
     * @throws ColumnChangeBlocked если смену колонки блокируют дети в работе
     */
    public function update(Task $task, array $links, array $fields): Task
    {
        $changes = [];

        $merged = $this->mergeLinks($task, $links);
        $resolved = $this->placement->resolveForUpdate($task, $merged);

        foreach ($resolved as $field => $value) {
            if ($task->{$field} !== $value) {
                $changes[$field] = $value;
            }
        }

        // Дети наследуют direction_id и initiative_id карточки. Если сменить
        // ссылку на родителя, у них останется денормализация старой ветки —
        // карточки «уедут» на чужую доску. Поэтому менять родителя можно
        // только у карточки без детей.
        if ($this->parentChanged($task, $merged)) {
            $children = $this->tasks->countChildren($task);

            if ($children > 0) {
                throw InvalidHierarchy::parentLockedByChildren($task->type, (int) $task->id, $children);
            }
        }

        $column = $fields['column'] ?? null;

        if ($column instanceof CardColumn) {
            $this->policy->ensureCanMove($task, $column);

            if ($task->column !== $column) {
                $changes['column'] = $column->value;
            }
        }

        // Простые поля сравниваются со значением из модели. Приоритет попадает
        // сюда же: ограничений по колонке и уровню у него нет, поэтому ему
        // достаточно общей проверки «значение изменилось».
        foreach (['title', 'description', 'slug', 'deadline'] as $field) {
            if (array_key_exists($field, $fields) && $task->{$field} !== $fields[$field]) {
                $changes[$field] = $fields[$field];
            }
        }

        // Приоритет — enum, поэтому сравнивается значение перечисления, а не
        // строка: в модель с атрибутом уходит сам enum, Eloquent приведёт его сам.
        $priority = $fields['priority'] ?? null;

        if ($priority instanceof CardPriority && $task->priority !== $priority) {
            $changes['priority'] = $priority;
        }

        // Степень проработки меняется независимо от колонки: флаг хранится
        // всегда и при смене колонки не сбрасывается — интерфейс просто не
        // показывает выбор вне беклога.
        if (array_key_exists('idea', $fields) && (bool) $task->idea !== $fields['idea']) {
            $changes['idea'] = $fields['idea'];
        }

        return $this->tasks->update($task, $changes);
    }

    /**
     * Переданная ссылка на родителя отличается от текущей.
     *
     * Дети ссылаются на карточку через initiative_id (проекты) или project_id
     * (задачи), поэтому достаточно сравнить эти два поля.
     *
     * @param  array{direction_id?: int|null, initiative_id?: int|null, project_id?: int|null}  $merged
     */
    private function parentChanged(Task $task, array $merged): bool
    {
        return ($merged['initiative_id'] ?? null) !== $task->initiative_id
            || ($merged['project_id'] ?? null) !== $task->project_id;
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
