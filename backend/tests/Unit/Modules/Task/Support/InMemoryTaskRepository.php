<?php

namespace Tests\Unit\Modules\Task\Support;

use App\Modules\Direction\Domain\Direction;
use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Domain\Exceptions\TaskNotFound;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardType;

/**
 * Тестовый двойник порта TaskRepository для Unit-тестов доменных правил.
 *
 * Приложение в Unit-тестах не поднято, поэтому модели наполняются напрямую
 * через setRawAttributes() и forceFill(): касты enum доступны и без БД,
 * но сохранение и запросы — нет.
 */
final class InMemoryTaskRepository implements TaskRepository
{
    /**
     * Карточки по id.
     *
     * @var array<int, Task>
     */
    private array $tasks = [];

    /**
     * Направления по id: id направления => id пользователя.
     *
     * @var array<int, int>
     */
    private array $directions = [];

    /**
     * Дочерние карточки в работе по id родителя — для проверки блокировок.
     *
     * @var array<int, int>
     */
    private array $childrenInProgress = [];

    /**
     * Общее число детей по id родителя.
     *
     * @var array<int, int>
     */
    private array $children = [];

    public function addDirection(int $id, int $userId): void
    {
        $this->directions[$id] = $userId;
    }

    public function add(Task $task): void
    {
        $this->tasks[(int) $task->id] = $task;
    }

    public function setChildren(int $parentId, int $inProgress, int $total): void
    {
        $this->childrenInProgress[$parentId] = $inProgress;
        $this->children[$parentId] = $total;
    }

    public function findForUser(int $userId, int $id): Task
    {
        $task = $this->tasks[$id] ?? null;

        if ($task === null || $task->user_id !== $userId) {
            throw TaskNotFound::withId($id);
        }

        return $task;
    }

    public function listForUser(int $userId, array $filters = []): array
    {
        return array_values(array_filter(
            $this->tasks,
            static fn (Task $task): bool => $task->user_id === $userId,
        ));
    }

    public function countChildrenInProgress(Task $task): int
    {
        return $this->childrenInProgress[(int) $task->id] ?? 0;
    }

    public function countChildren(Task $task): int
    {
        return $this->children[(int) $task->id] ?? 0;
    }

    public function findParent(int $userId, int $id, CardType $type): ?Task
    {
        $task = $this->tasks[$id] ?? null;

        if ($task === null || $task->user_id !== $userId || $task->type !== $type) {
            return null;
        }

        return $task;
    }

    public function create(int $userId, array $attributes): Task
    {
        throw new \RuntimeException('InMemoryTaskRepository не поддерживает создание карточек.');
    }

    public function update(Task $task, array $changes): Task
    {
        $task->forceFill($changes);

        return $task;
    }

    public function delete(Task $task): void
    {
        unset($this->tasks[(int) $task->id]);
    }

    public function directionExists(int $userId, int $directionId): bool
    {
        return ($this->directions[$directionId] ?? null) === $userId;
    }

    /**
     * Собрать карточку без обращения к БД.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function task(int $id, int $userId, array $attributes = []): Task
    {
        $task = new Task;
        $task->setRawAttributes(array_merge([
            'id' => $id,
            'user_id' => $userId,
            'direction_id' => null,
            'initiative_id' => null,
            'project_id' => null,
            'type' => CardType::Task->value,
            'column' => 'backlog',
            'title' => 'Карточка '.$id,
            'description' => null,
        ], $attributes), true);

        return $task;
    }

    /**
     * Собрать направление без обращения к БД.
     */
    public static function direction(int $id, int $userId): Direction
    {
        $direction = new Direction;
        $direction->setRawAttributes(['id' => $id, 'user_id' => $userId, 'color' => 'c1'], true);

        return $direction;
    }
}
