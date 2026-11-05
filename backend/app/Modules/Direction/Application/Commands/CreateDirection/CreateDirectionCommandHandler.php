<?php

namespace App\Modules\Direction\Application\Commands\CreateDirection;

use App\Modules\Direction\Application\Results\DirectionResult;
use App\Modules\Direction\Domain\Services\DirectionManager;

/**
 * Хендлер команды создания направления.
 *
 * Вызывается шиной команд: она оборачивает хендлер в транзакцию, поэтому
 * направление и запись журнала создаются атомарно.
 */
final readonly class CreateDirectionCommandHandler
{
    public function __construct(
        private DirectionManager $directions,
    ) {}

    public function handle(CreateDirectionCommand $command): DirectionResult
    {
        $direction = $this->directions->create($command->userId, $command->title, $command->color);

        return DirectionResult::fromDirection($direction);
    }
}
