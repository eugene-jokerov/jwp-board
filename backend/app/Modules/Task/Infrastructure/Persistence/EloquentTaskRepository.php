<?php

namespace App\Modules\Task\Infrastructure\Persistence;

use App\Modules\Direction\Domain\Direction;
use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Domain\Exceptions\TaskNotFound;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent-реализация порта TaskRepository.
 *
 * Все запросы ограничены user_id: граница пользователя — часть контракта порта.
 * Денормализованные direction_id и initiative_id позволяют проверять
 * иерархию и детей в работе без рекурсивных запросов.
 */
final class EloquentTaskRepository implements TaskRepository
{
    public function findForUser(int $userId, int $id): Task
    {
        $task = Task::query()
            ->where('user_id', $userId)
            ->whereKey($id)
            ->first();

        if ($task === null) {
            throw TaskNotFound::withId($id);
        }

        return $task;
    }

    public function listForUser(int $userId, array $filters = []): array
    {
        $query = Task::query()->where('user_id', $userId);

        $type = $filters['type'] ?? null;

        if ($type instanceof CardType) {
            $query->where('type', $type->value);
        }

        $column = $filters['column'] ?? null;

        if ($column instanceof CardColumn) {
            $query->where('column', $column->value);
        }

        foreach (['direction_id', 'initiative_id', 'project_id'] as $field) {
            $value = $filters[$field] ?? null;

            // 0 и отрицательные — не идентификаторы: фильтр не применяем.
            if ($value !== null && (int) $value > 0) {
                $query->where($field, (int) $value);
            }
        }

        return $query->orderBy('id')->get()->all();
    }

    public function countChildrenInProgress(Task $task): int
    {
        return $this->childrenQuery($task)
            ->where('column', CardColumn::InProgress->value)
            ->count();
    }

    /**
     * Дети в работе для пачки карточек: два GROUP BY-запроса (проекты по
     * initiative_id и задачи по project_id) вместо count() на каждую карточку.
     *
     * @param  list<Task>  $tasks
     * @return array<int, int>
     */
    public function countChildrenInProgressFor(array $tasks): array
    {
        $initiativeIds = [];
        $projectIds = [];

        foreach ($tasks as $task) {
            match ($task->type) {
                CardType::Initiative => $initiativeIds[] = (int) $task->id,
                CardType::Project => $projectIds[] = (int) $task->id,
                CardType::Task => null,
            };
        }

        $counts = array_fill_keys(array_merge($initiativeIds, $projectIds), 0);

        if ($initiativeIds !== []) {
            $this->countByParent('initiative_id', $initiativeIds, $counts);
        }

        if ($projectIds !== []) {
            $this->countByParent('project_id', $projectIds, $counts);
        }

        return $counts;
    }

    public function countChildren(Task $task): int
    {
        return $this->childrenQuery($task)->count();
    }

    public function findParent(int $userId, int $id, CardType $type): ?Task
    {
        return Task::query()
            ->where('user_id', $userId)
            ->where('type', $type->value)
            ->whereKey($id)
            ->first();
    }

    public function create(int $userId, array $attributes): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'direction_id' => $attributes['direction_id'],
            'initiative_id' => $attributes['initiative_id'],
            'project_id' => $attributes['project_id'],
            'type' => $attributes['type']->value,
            'column' => $attributes['column']->value,
            'title' => $attributes['title'],
            'description' => $attributes['description'],
        ]);
    }

    public function update(Task $task, array $changes): Task
    {
        if ($changes !== []) {
            $task->fill($changes);
            $task->save();
        }

        return $task;
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    public function directionExists(int $userId, int $directionId): bool
    {
        return Direction::query()
            ->where('user_id', $userId)
            ->whereKey($directionId)
            ->exists();
    }

    /**
     * Дети карточки: у инициативы проекты, у проекта задачи, у задачи никого.
     *
     * @return Builder<Task>
     */
    private function childrenQuery(Task $task): Builder
    {
        return match ($task->type) {
            CardType::Initiative => Task::query()->where('initiative_id', $task->id),
            CardType::Project => Task::query()->where('project_id', $task->id),
            CardType::Task => Task::query()->whereRaw('1 = 0'),
        };
    }

    /**
     * Посчитать детей в работе по родителям и дописать в $counts.
     *
     * @param  'initiative_id'|'project_id'  $parentField
     * @param  list<int>  $parentIds
     * @param  array<int, int>  $counts
     */
    private function countByParent(string $parentField, array $parentIds, array &$counts): void
    {
        $rows = Task::query()
            ->selectRaw($parentField.' as parent_id, count(*) as aggregate')
            ->whereIn($parentField, $parentIds)
            ->where('column', CardColumn::InProgress->value)
            ->groupBy($parentField)
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            $counts[(int) $row->parent_id] = (int) $row->aggregate;
        }
    }
}
