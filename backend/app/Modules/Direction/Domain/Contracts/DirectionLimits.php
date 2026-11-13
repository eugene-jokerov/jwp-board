<?php

namespace App\Modules\Direction\Domain\Contracts;

/**
 * Лимиты направлений из настроек приложения.
 *
 * Домина знает правило «на пользователя не больше N направлений», а откуда
 * N взято (config('direction.max_per_user')) — деталь приложения. Поэтому
 * значение приходит через порт, который реализует конфигурация.
 */
interface DirectionLimits
{
    /**
     * Максимум направлений на пользователя.
     */
    public function maxPerUser(): int;
}
