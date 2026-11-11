<?php

namespace App\Modules\Direction\Application\Commands\DeleteDirectionForAdmin;

/**
 * Команда удаления направления из админки.
 */
final readonly class DeleteDirectionForAdminCommand
{
    public function __construct(
        public int $ownerId,
        public int $directionId,
    ) {}
}
