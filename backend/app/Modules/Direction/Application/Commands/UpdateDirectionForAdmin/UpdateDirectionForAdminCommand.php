<?php

namespace App\Modules\Direction\Application\Commands\UpdateDirectionForAdmin;

use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;

/**
 * Команда изменения направления из админки.
 *
 * null означает «не менять» — как и в UpdateDirectionCommand.
 */
final readonly class UpdateDirectionForAdminCommand
{
    public function __construct(
        public int $ownerId,
        public int $directionId,
        public ?DirectionTitle $title = null,
        public ?DirectionColor $color = null,
    ) {}
}
