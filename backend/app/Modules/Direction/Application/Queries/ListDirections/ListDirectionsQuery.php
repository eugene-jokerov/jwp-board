<?php

namespace App\Modules\Direction\Application\Queries\ListDirections;

/**
 * Запрос списка направлений пользователя.
 */
final readonly class ListDirectionsQuery
{
    public function __construct(
        public int $userId,
    ) {}
}
