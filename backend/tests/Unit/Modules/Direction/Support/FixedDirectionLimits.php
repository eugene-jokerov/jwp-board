<?php

namespace Tests\Unit\Modules\Direction\Support;

use App\Modules\Direction\Domain\Contracts\DirectionLimits;

/**
 * Лимит направлений с фиксированным значением: тест не зависит от конфигурации.
 */
final readonly class FixedDirectionLimits implements DirectionLimits
{
    public function __construct(
        private int $limit,
    ) {}

    public function maxPerUser(): int
    {
        return $this->limit;
    }
}
