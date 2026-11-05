<?php

namespace App\Modules\Direction\Application\Queries\ShowDirection;

use App\Modules\Direction\Application\Results\DirectionResult;
use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\Exceptions\DirectionNotFound;

/**
 * Хендлер запроса одного направления.
 */
final readonly class ShowDirectionQueryHandler
{
    public function __construct(
        private DirectionRepository $directions,
    ) {}

    /**
     * @throws DirectionNotFound если направления нет или оно чужое
     */
    public function handle(ShowDirectionQuery $query): DirectionResult
    {
        return DirectionResult::fromDirection(
            $this->directions->findForUser($query->userId, $query->directionId),
        );
    }
}
