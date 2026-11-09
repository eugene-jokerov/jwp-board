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
        $query = $this->filteredQuery($userId, $filters);

        // `asc` по умолчанию — прежний порядок доски; `desc` нужен закрытым
        // колонкам, где сверху показываем самые свежие карточки.
        $query->orderBy('id', ($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc');

        $limit = $filters['limit'] ?? null;

        if ($limit !== null && (int) $limit > 0) {
            $query->limit((int) $limit);

            $offset = $filters['offset'] ?? 0;

            if ((int) $offset > 0) {
                $query->offset((int) $offset);
            }
        }

        return $query->get()->all();
    }

    public function countForUser(int $userId, array $filters = []): int
    {
        return $this->filteredQuery($userId, $filters)->count();
    }

    public function listInitialForUser(int $userId, array $filters, int $perLevelLimit): array
    {
        // Открытые колонки отдаются целиком — это основной рабочий экран доски.
        $open = $this->filteredQuery($userId, $filters)
            ->whereIn('column', $this->openColumnValues())
            ->orderBy('id', 'asc')
            ->get()
            ->all();

        $closed = $this->closedCardsOfInitialLoad($userId, $filters, $perLevelLimit);

        // Порядок ответа детерминирован: открытые — в порядке создания,
        // закрытые — свежие сверху, пары идут в порядке перечисления.
        return [...$open, ...$closed];
    }

    public function countClosedByLevelForUser(int $userId, array $filters = []): array
    {
        $rows = $this->filteredQuery($userId, $filters)
            ->select(['type', 'column'])
            ->selectRaw('count(*) as aggregate')
            ->whereIn('column', $this->closedColumnValues())
            ->groupBy('type', 'column')
            ->toBase()
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->column][(string) $row->type] = (int) $row->aggregate;
        }

        return $counts;
    }

    public function countByColumnForUser(int $userId, array $filters = []): array
    {
        // `column` — зарезервированное слово в MySQL, но билдер квотирует
        // имена колонок сам, поэтому select/groupBy можно писать буквально.
        $rows = $this->filteredQuery($userId, $filters)
            ->select('column')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('column')
            ->toBase()
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->column] = (int) $row->aggregate;
        }

        return $counts;
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
     * Закрытые карточки первичной загрузки: не больше $perLevelLimit на каждую
     * пару «уровень + колонка», свежие сверху.
     *
     * На каждую пару — своя ветка UNION ALL со своим LIMIT. Ветка идёт по
     * индексу (user_id, type, column) в нужном порядке и читает ровно
     * $perLevelLimit строк, поэтому время зависит от размера порции, а не от
     * того, сколько закрытых карточек накопилось у пользователя: оконная
     * функция ROW_NUMBER() читала бы всю закрытую колонку и сортировала её.
     *
     * Фильтры накладываются внутри каждой ветки — так «показать ещё» внутри
     * свимлайна и фильтр по направлению тоже ограничены порцией.
     *
     * @param  array<string, mixed>  $filters
     * @return list<Task>
     */
    private function closedCardsOfInitialLoad(int $userId, array $filters, int $perLevelLimit): array
    {
        $limit = max(1, $perLevelLimit);
        $columns = $this->closedColumnValues();
        $type = $filters['type'] ?? null;

        // Явный фильтр по уровню сужает набор пар: остальные уровни и так
        // не попадут в выборку, а лишние ветки — лишние обращения к таблице.
        $types = $type instanceof CardType
            ? [$type->value]
            : array_map(static fn (CardType $cardType): string => $cardType->value, CardType::cases());

        $union = null;

        foreach ($columns as $column) {
            foreach ($types as $cardType) {
                $branch = $this->filteredQuery($userId, $filters)
                    ->where('column', $column)
                    ->where('type', $cardType)
                    ->orderByDesc('id')
                    ->limit($limit);

                $union = $union === null ? $branch : $union->unionAll($branch);
            }
        }

        if ($union === null) {
            return [];
        }

        // Порядок ответа детерминирован и одинаков между парами: ветки идут в
        // порядке колонок и уровней, внутри — свежие сверху. Сортировку задаём
        // на объединении: ветки по отдельности уже упорядочены, но UNION ALL
        // порядок не гарантирует.
        return Task::query()
            ->fromSub($union, 'tasks')
            ->orderBy('column')
            ->orderBy('type')
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    /**
     * Значения закрытых колонок (Готово, Отмена).
     *
     * @return list<string>
     */
    private function closedColumnValues(): array
    {
        return array_values(array_map(
            static fn (CardColumn $column): string => $column->value,
            array_filter(CardColumn::ordered(), static fn (CardColumn $column): bool => $column->isClosed()),
        ));
    }

    /**
     * Значения открытых колонок (Беклог, В работе).
     *
     * @return list<string>
     */
    private function openColumnValues(): array
    {
        return array_values(array_map(
            static fn (CardColumn $column): string => $column->value,
            array_filter(CardColumn::ordered(), static fn (CardColumn $column): bool => ! $column->isClosed()),
        ));
    }

    /**
     * Запрос карточек пользователя с фильтрами, без сортировки и страницы.
     *
     * Общий для списка и подсчёта: условия фильтрации не должны расходиться
     * между «что отдаём» и «сколько всего есть».
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Task>
     */
    private function filteredQuery(int $userId, array $filters): Builder
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

        return $query;
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
