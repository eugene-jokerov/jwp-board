<?php

namespace App\Modules\Task\Application\Commands\UpdateTask;

use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Task\Application\Results\TaskResult;
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
        private DirectionRepository $directions,
    ) {}

    public function handle(UpdateTaskCommand $command): TaskResult
    {
        $task = $this->tasks->findForUser($command->userId, $command->taskId);
        $updated = $this->editor->update($task, $command->links, $command->changes);

        return TaskResult::fromTask(
            $updated,
            $this->policy->allowedTransitions($updated),
            $this->colorOf($command->userId, $updated->direction_id),
        );
    }

    private function colorOf(int $userId, ?int $directionId): ?DirectionColor
    {
        if ($directionId === null) {
            return null;
        }

        return $this->directions->findForUser($userId, $directionId)->color;
    }
}
