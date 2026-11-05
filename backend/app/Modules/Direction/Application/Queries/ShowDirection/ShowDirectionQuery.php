<?php

namespace App\Modules\Direction\Application\Queries\ShowDirection;

/**
 * Запрос одного направления пользователя.
 */
final readonly class ShowDirectionQuery
{
    public function __construct(
        public int $userId,
        public int $directionId,
    ) {}
}
