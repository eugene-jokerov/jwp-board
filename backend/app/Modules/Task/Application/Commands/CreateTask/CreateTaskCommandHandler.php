<?php

namespace App\Modules\Task\Application\Commands\CreateTask;

use App\Modules\Task\Application\Results\TaskResult;
use App\Modules\Task\Application\Services\TaskDirectionColor;
use App\Modules\Task\Application\Services\TaskFactory;
use App\Modules\Task\Domain\Services\ColumnTransitionPolicy;
use LogicException;

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
        // Владельца задаёт вход: в API — аутентификация, в админке — форма.
        // Команда без владельца — ошибка сборки команды, а не данных запроса.
        $userId = $command->userId ?? throw new LogicException('Не указан владелец создаваемой карточки.');

        $task = $this->factory->create(
            $userId,
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
            $this->colors->of($userId, $task->direction_id),
        );
    }
}
