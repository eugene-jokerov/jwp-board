<?php

namespace App\Modules\Task\Application\Queries\ListTasks;

use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Task\Application\Results\TaskResult;
use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Domain\Services\ColumnTransitionPolicy;

/**
 * Хендлер запроса списка карточек.
 *
 * Каждая карточка отдаётся вместе с признаками, которые нужны доске:
 * цветом направления и списком колонок, доступных для перехода. Список
 * не постраничный: доска показывает карточки всех уровней сразу, а объём
 * ограничен рамками одного пользователя.
 */
final readonly class ListTasksQueryHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private ColumnTransitionPolicy $policy,
        private DirectionRepository $directions,
    ) {}

    /**
     * @return list<TaskResult>
     */
    public function handle(ListTasksQuery $query): array
    {
        $tasks = $this->tasks->listForUser($query->userId, [
            'type' => $query->type,
            'column' => $query->column,
            'direction_id' => $query->directionId,
            'initiative_id' => $query->initiativeId,
            'project_id' => $query->projectId,
        ]);

        $colors = $this->colors($query->userId);

        return array_map(
            fn ($task): TaskResult => TaskResult::fromTask(
                $task,
                $this->policy->allowedTransitions($task),
                $task->direction_id === null ? null : ($colors[$task->direction_id] ?? null),
            ),
            $tasks,
        );
    }

    /**
     * Цвета направлений пользователя по id — один запрос на весь список.
     *
     * @return array<int, DirectionColor>
     */
    private function colors(int $userId): array
    {
        $colors = [];

        foreach ($this->directions->allForUser($userId) as $direction) {
            $colors[(int) $direction->id] = $direction->color;
        }

        return $colors;
    }
}
