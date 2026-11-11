<?php

namespace App\Modules\Task\Application\Commands\CreateTaskForAdmin;

use App\Modules\Task\Application\Commands\CreateTask\CreateTaskCommand;

/**
 * Команда создания карточки из админки.
 *
 * Отличие от CreateTaskCommand только в том, что владельца выбирает
 * администратор: в API он берётся из аутентификации. Остальные поля и
 * правила те же, поэтому команда — обёртка, а не копия набора полей.
 */
final readonly class CreateTaskForAdminCommand
{
    public function __construct(
        public int $ownerId,
        public CreateTaskCommand $task,
    ) {}

    /**
     * Команда создания с владельцем из формы админки.
     */
    public function forBus(): CreateTaskCommand
    {
        return $this->task->withUserId($this->ownerId);
    }
}
