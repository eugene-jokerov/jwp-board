<?php

namespace App\Modules\Direction\Domain\Exceptions;

use RuntimeException;

/**
 * Направление нельзя удалить: в нём есть карточки.
 *
 * Правило предметной области: направление — корень иерархии, и удалять его
 * можно только пустым. Сначала пользователь удаляет инициативы, которые в нём
 * лежат. На границе приложения переводится в 409 Conflict.
 */
final class DirectionHasCards extends RuntimeException
{
    public static function withCards(int $directionId, int $cards): self
    {
        return new self(sprintf(
            'В направлении есть карточки (%d). Удаление запрещено: сначала удалите карточки.',
            $cards,
        ));
    }
}
