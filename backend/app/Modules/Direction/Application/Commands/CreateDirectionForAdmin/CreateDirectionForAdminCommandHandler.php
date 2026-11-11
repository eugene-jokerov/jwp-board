<?php

namespace App\Modules\Direction\Application\Commands\CreateDirectionForAdmin;

use App\Modules\Direction\Application\Results\DirectionResult;
use App\Modules\Direction\Domain\Services\DirectionManager;

/**
 * Хендлер команды создания направления из админки.
 */
final readonly class CreateDirectionForAdminCommandHandler
{
    public function __construct(
        private DirectionManager $directions,
    ) {}

    public function handle(CreateDirectionForAdminCommand $command): DirectionResult
    {
        $direction = $this->directions->create($command->ownerId, $command->title, $command->color);

        return DirectionResult::fromDirection($direction);
    }
}
