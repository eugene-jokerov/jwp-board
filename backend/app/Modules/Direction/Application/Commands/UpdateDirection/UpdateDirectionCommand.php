<?php

namespace App\Modules\Direction\Application\Commands\UpdateDirection;

use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;

/**
 * Команда изменения направления.
 *
 * Поля необязательны: null означает «не менять». Так один сценарий покрывает
 * и смену названия, и смену цвета, и PATCH с обоими полями.
 */
final readonly class UpdateDirectionCommand
{
    public function __construct(
        public int $userId,
        public int $directionId,
        public ?DirectionTitle $title = null,
        public ?DirectionColor $color = null,
    ) {}

    /**
     * @param  array{title?: string|null, color?: string|null}  $payload
     */
    public static function fromArray(int $userId, int $directionId, array $payload): self
    {
        $title = $payload['title'] ?? null;
        $color = $payload['color'] ?? null;

        return new self(
            $userId,
            $directionId,
            $title === null ? null : DirectionTitle::fromString($title),
            $color === null ? null : DirectionColor::fromKey($color),
        );
    }
}
