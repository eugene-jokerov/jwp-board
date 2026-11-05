<?php

namespace App\Modules\Task\Domain\Exceptions;

use RuntimeException;

/**
 * Карточка не найдена.
 *
 * Бросается и для несуществующего id, и для карточки другого пользователя:
 * снаружи оба случая неразличимы (404).
 */
final class TaskNotFound extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Карточка %d не найдена.', $id));
    }
}
