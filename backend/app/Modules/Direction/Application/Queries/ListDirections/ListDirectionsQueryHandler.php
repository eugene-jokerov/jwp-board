<?php

namespace App\Modules\Direction\Application\Queries\ListDirections;

use App\Modules\Direction\Application\Results\DirectionResult;
use App\Modules\Direction\Domain\Contracts\DirectionRepository;

/**
 * Хендлер запроса списка направлений.
 *
 * Список не постраничный: палитра направлений конечна и невелика, а доска
 * всё равно показывает их все на стратегическом уровне.
 */
final readonly class ListDirectionsQueryHandler
{
    public function __construct(
        private DirectionRepository $directions,
    ) {}

    /**
     * @return list<DirectionResult>
     */
    public function handle(ListDirectionsQuery $query): array
    {
        return array_map(
            static fn ($direction): DirectionResult => DirectionResult::fromDirection($direction),
            $this->directions->allForUser($query->userId),
        );
    }
}
