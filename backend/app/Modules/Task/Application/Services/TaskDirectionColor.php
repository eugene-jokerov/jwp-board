<?php

namespace App\Modules\Task\Application\Services;

use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;

/**
 * Цвет направления для ответа по карточке.
 *
 * Направление карточки может исчезнуть раньше неё самой: удаление направления
 * разрывает связь через FK `nullOnDelete`, а история карточек живёт дольше
 * направлений. Отсутствие направления — не ошибка сценария: карточка просто
 * остаётся без цвета, поэтому репозиторий направлений здесь не бросает 404.
 */
final readonly class TaskDirectionColor
{
    public function __construct(
        private DirectionRepository $directions,
    ) {}

    /**
     * Цвет направления пользователя или null, если направления уже нет.
     */
    public function of(int $userId, ?int $directionId): ?DirectionColor
    {
        if ($directionId === null) {
            return null;
        }

        foreach ($this->directions->allForUser($userId) as $direction) {
            if ((int) $direction->id === $directionId) {
                return $direction->color;
            }
        }

        return null;
    }
}
