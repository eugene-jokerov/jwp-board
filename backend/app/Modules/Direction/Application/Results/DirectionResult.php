<?php

namespace App\Modules\Direction\Application\Results;

use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;

/**
 * Результат сценария над направлением.
 *
 * Прикладной слой отдаёт наружу простой снимок, а не Eloquent-модель:
 * HTTP-адаптер не получает доступ к запросам и сохранению.
 */
final readonly class DirectionResult
{
    public function __construct(
        public int $id,
        public string $title,
        public DirectionColor $color,
    ) {}

    /**
     * Снимок направления из доменной сущности.
     */
    public static function fromDirection(Direction $direction): self
    {
        return new self(
            (int) $direction->id,
            $direction->title,
            $direction->color,
        );
    }
}
