<?php

namespace App\Modules\Direction\Application\Commands\UpdateDirection;

use App\Modules\Direction\Application\Results\DirectionResult;
use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\Services\DirectionManager;

/**
 * Хендлер команды изменения направления.
 */
final readonly class UpdateDirectionCommandHandler
{
    public function __construct(
        private DirectionRepository $directions,
        private DirectionManager $manager,
    ) {}

    public function handle(UpdateDirectionCommand $command): DirectionResult
    {
        $direction = $this->directions->findForUser($command->userId, $command->directionId);
        $updated = $this->manager->update($direction, $command->title, $command->color);

        return DirectionResult::fromDirection($updated);
    }
}
