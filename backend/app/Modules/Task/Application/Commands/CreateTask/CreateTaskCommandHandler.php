<?php

namespace App\Modules\Task\Application\Commands\CreateTask;

use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Task\Application\Results\TaskResult;
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
        private DirectionRepository $directions,
    ) {}

    public function handle(CreateTaskCommand $command): TaskResult
    {
        $task = $this->factory->create(
            $command->userId,
            $command->type,
            $command->column,
            $command->title,
            $command->description,
            $command->directionId,
            $command->iniciativeId,
            $command->projectId,
        );

        return TaskResult::fromTask(
            $task,
            $this->policy->allowedTransitions($task),
            $this->colorOf($command->userId, $task->direction_id),
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
