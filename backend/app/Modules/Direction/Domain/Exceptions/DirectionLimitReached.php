<?php

namespace App\Modules\Direction\Domain\Exceptions;

use RuntimeException;

/**
 * Направление нельзя создать: у пользователя уже достигнут лимит направлений.
 *
 * Правило предметной области: палитра направлений конечна, а доска должна
 * оставаться обозримой, поэтому число направлений на пользователя ограничено.
 * Лимит — не конфликт состояния (как «в направлении есть карточки»), а ошибка
 * входных данных: на границе приложения переводится в 422 Unprocessable Entity.
 */
final class DirectionLimitReached extends RuntimeException
{
    public static function withLimit(int $limit): self
    {
        return new self(sprintf(
            'Достигнут лимит направлений (%d). Удалите ненужное направление, чтобы добавить новое.',
            $limit,
        ));
    }
}
