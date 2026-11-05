<?php

namespace App\Modules\Task\Application\Queries\ShowTask;

/**
 * Запрос одной карточки пользователя.
 */
final readonly class ShowTaskQuery
{
    public function __construct(
        public int $userId,
        public int $taskId,
    ) {}
}
