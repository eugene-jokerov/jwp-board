<?php

namespace App\Modules\Direction\Application\Commands\DeleteDirection;

/**
 * Команда удаления направления.
 */
final readonly class DeleteDirectionCommand
{
    public function __construct(
        public int $userId,
        public int $directionId,
    ) {}
}
