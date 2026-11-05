<?php

namespace App\Modules\Task\Application\Commands\DeleteTask;

/**
 * Команда удаления карточки.
 */
final readonly class DeleteTaskCommand
{
    public function __construct(
        public int $userId,
        public int $taskId,
    ) {}
}
