<?php

namespace App\Modules\Direction\Application\Commands\DeleteDirectionForAdmin;

use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\Exceptions\DirectionHasCards;
use App\Modules\Direction\Domain\Services\DirectionManager;

/**
 * Хендлер команды удаления направления из админки.
 *
 * Каскада нет намеренно: направление — корень иерархии, и ради удаления
 * направления в админке сначала удаляется его карточка (с поддеревом),
 * а потом пустое направление. Так модерируемая чистка остаётся явной и
 * ничего не уносит «молча».
 */
final readonly class DeleteDirectionForAdminCommandHandler
{
    public function __construct(
        private DirectionRepository $directions,
        private DirectionManager $manager,
    ) {}

    /**
     * @throws DirectionHasCards если в направлении есть карточки
     */
    public function handle(DeleteDirectionForAdminCommand $command): void
    {
        $this->manager->delete(
            $this->directions->findForUser($command->ownerId, $command->directionId),
        );
    }
}
