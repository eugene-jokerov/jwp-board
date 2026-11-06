<?php

namespace App\Modules\Task\Application\Commands\UpdateTask;

use App\Modules\Task\Application\Results\TaskResult;
use App\Modules\Task\Application\Services\TaskDirectionColor;
use App\Modules\Task\Application\Services\TaskEditor;
use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Domain\Services\ColumnTransitionPolicy;

/**
 * Хендлер команды изменения карточки.
 */
final readonly class UpdateTaskCommandHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskEditor $editor,
        private ColumnTransitionPolicy $policy,
        private TaskDirectionColor $colors,
    ) {}

    public function handle(UpdateTaskCommand $command): TaskResult
    {
        $task = $this->tasks->findForUser($command->userId, $command->taskId);
        $updated = $this->editor->update($task, $command->links, $command->changes);

        return TaskResult::fromTask(
            $updated,
            $this->policy->allowedTransitions($updated),
            $this->colors->of($command->userId, $updated->direction_id),
        );
    }
}
