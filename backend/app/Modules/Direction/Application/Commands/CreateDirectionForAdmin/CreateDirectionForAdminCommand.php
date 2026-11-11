<?php

namespace App\Modules\Direction\Application\Commands\CreateDirectionForAdmin;

use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;

/**
 * Команда создания направления из админки.
 *
 * Отличие от CreateDirectionCommand только в источнике владельца: в API его
 * даёт аутентификация, здесь — выбор администратора. Лимит направлений
 * (config('direction.max_per_user')) при этом действует так же — решение
 * принимает DirectionManager, а не точка входа.
 */
final readonly class CreateDirectionForAdminCommand
{
    public function __construct(
        public int $ownerId,
        public DirectionTitle $title,
        public DirectionColor $color,
    ) {}
}
