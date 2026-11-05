<?php

namespace App\Modules\Direction\Application\Commands\DeleteDirection;

use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\Exceptions\DirectionHasCards;
use App\Modules\Direction\Domain\Services\DirectionManager;

/**
 * Хендлер команды удаления направления.
 *
 * Проверка «направление пустое» живёт в доменном сервисе: хендлер только
 * достаёт сущность и передаёт её менеджеру.
 */
final readonly class DeleteDirectionCommandHandler
{
    public function __construct(
        private DirectionRepository $directions,
        private DirectionManager $manager,
    ) {}

    /**
     * @throws DirectionHasCards если в направлении есть карточки
     */
    public function handle(DeleteDirectionCommand $command): void
    {
        $direction = $this->directions->findForUser($command->userId, $command->directionId);

        $this->manager->delete($direction);
    }
}
