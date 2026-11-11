<?php

namespace App\Modules\Task\Application\Commands\UpdateTaskForAdmin;

use App\Modules\Task\Application\Results\TaskResult;
use App\Modules\Task\Application\Services\TaskDirectionColor;
use App\Modules\Task\Application\Services\TaskEditor;
use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Domain\Services\ColumnTransitionPolicy;

/**
 * Хендлер команды изменения карточки из админки.
 *
 * Повторяет UpdateTaskCommandHandler: доменные правила (TaskEditor и
 * ColumnTransitionPolicy) не обходятся админкой — иерархия и запрет смены
 * колонки при детях в работе действуют одинаково для API и панели.
 */
final readonly class UpdateTaskForAdminCommandHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskEditor $editor,
        private ColumnTransitionPolicy $policy,
        private TaskDirectionColor $colors,
    ) {}

    public function handle(UpdateTaskForAdminCommand $command): TaskResult
    {
        $task = $this->tasks->findForUser($command->ownerId, $command->taskId);
        $updated = $this->editor->update($task, $command->links, $command->changes);

        return TaskResult::fromTask(
            $updated,
            $this->policy->allowedTransitions($updated),
            $this->colors->of($command->ownerId, $updated->direction_id),
        );
    }
}
