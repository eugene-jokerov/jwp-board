<?php

namespace App\Modules\Direction\Application\Commands\UpdateDirectionForAdmin;

use App\Modules\Direction\Application\Results\DirectionResult;
use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\Services\DirectionManager;

/**
 * Хендлер команды изменения направления из админки.
 *
 * Владелец направления не меняется: карточки ссылаются на направление по id,
 * а «перенос» направления к другому пользователю увёл бы вместе с ним чужие
 * карточки. Поэтому поиск идёт в границах владельца из формы.
 */
final readonly class UpdateDirectionForAdminCommandHandler
{
    public function __construct(
        private DirectionRepository $directions,
        private DirectionManager $manager,
    ) {}

    public function handle(UpdateDirectionForAdminCommand $command): DirectionResult
    {
        $direction = $this->directions->findForUser($command->ownerId, $command->directionId);

        return DirectionResult::fromDirection(
            $this->manager->update($direction, $command->title, $command->color),
        );
    }
}
