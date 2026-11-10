<?php

namespace App\Modules\Task\Application\Commands\CreateTask;

use App\Modules\Task\Application\Results\TaskResult;
use App\Modules\Task\Application\Services\TaskDirectionColor;
use App\Modules\Task\Application\Services\TaskFactory;
use App\Modules\Task\Domain\Services\ColumnTransitionPolicy;

/**
 * Хендлер команды создания карточки.
 *
 * Вызывается шиной команд (транзакция на команду), поэтому карточка
 * и запись журнала создаются атомарно.
 */
final readonly class CreateTaskCommandHandler
{
    public function __construct(
        private TaskFactory $factory,
        private ColumnTransitionPolicy $policy,
        private TaskDirectionColor $colors,
    ) {}

    public function handle(CreateTaskCommand $command): TaskResult
    {
        $task = $this->factory->create(
            $command->userId,
            $command->type,
            $command->column,
            $command->priority,
            $command->title,
            $command->description,
            $command->directionId,
            $command->initiativeId,
            $command->projectId,
            $command->deadline,
            $command->slug,
            $command->idea,
        );

        return TaskResult::fromTask(
            $task,
            $this->policy->allowedTransitions($task),
            $this->colors->of($command->userId, $task->direction_id),
        );
    }
}
