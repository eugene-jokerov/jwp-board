<?php

namespace App\Modules\Task\Application\Commands\CreateTaskForAdmin;

use App\Modules\Task\Application\Commands\CreateTask\CreateTaskCommandHandler;
use App\Modules\Task\Application\Results\TaskResult;

/**
 * Хендлер команды создания карточки из админки.
 *
 * Исполнение сценария не дублируется: команда подставляет владельца и уходит
 * в хендлер CreateTaskCommand, поэтому иерархия (TaskPlacement), журнал
 * истории и транзакция — те же, что у создания через API.
 */
final readonly class CreateTaskForAdminCommandHandler
{
    public function __construct(
        private CreateTaskCommandHandler $inner,
    ) {}

    public function handle(CreateTaskForAdminCommand $command): TaskResult
    {
        return $this->inner->handle($command->forBus());
    }
}
