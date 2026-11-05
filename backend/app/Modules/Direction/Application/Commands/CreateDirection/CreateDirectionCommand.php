<?php

namespace App\Modules\Direction\Application\Commands\CreateDirection;

use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;

/**
 * Команда создания направления.
 *
 * Пользователь приходит из аутентификации, а не из тела запроса: подставить
 * чужой user_id через API нельзя.
 */
final readonly class CreateDirectionCommand
{
    public function __construct(
        public int $userId,
        public DirectionTitle $title,
        public DirectionColor $color,
    ) {}

    /**
     * @param  array{title: string, color: string}  $payload  провалидированные данные запроса
     */
    public static function fromArray(int $userId, array $payload): self
    {
        return new self(
            $userId,
            DirectionTitle::fromString($payload['title']),
            DirectionColor::fromKey($payload['color']),
        );
    }
}
