<?php

namespace App\Modules\Direction\Infrastructure\Config;

use App\Modules\Direction\Domain\Contracts\DirectionLimits;

/**
 * Лимиты направлений из конфигурации приложения.
 *
 * config() отдаёт mixed, но config/direction.php уже нормализовал значение
 * до положительного int, поэтому приведение безопасно.
 */
final readonly class ConfigDirectionLimits implements DirectionLimits
{
    public function maxPerUser(): int
    {
        return (int) config('direction.max_per_user');
    }
}
