<?php

namespace App\Modules\Task\Application\Queries\ShowTask;

use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Task\Application\Results\TaskResult;
use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Domain\Exceptions\TaskNotFound;
use App\Modules\Task\Domain\Services\ColumnTransitionPolicy;

/**
 * Хендлер запроса одной карточки.
 */
final readonly class ShowTaskQueryHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private ColumnTransitionPolicy $policy,
        private DirectionRepository $directions,
    ) {}

    /**
     * @throws TaskNotFound если карточки нет или она чужая
     */
    public function handle(ShowTaskQuery $query): TaskResult
    {
        $task = $this->tasks->findForUser($query->userId, $query->taskId);

        $color = null;

        if ($task->direction_id !== null) {
            $color = $this->directions->findForUser($query->userId, $task->direction_id)->color;
        }

        return TaskResult::fromTask($task, $this->policy->allowedTransitions($task), $color);
    }
}
