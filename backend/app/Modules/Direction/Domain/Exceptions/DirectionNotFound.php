<?php

namespace App\Modules\Direction\Domain\Exceptions;

use RuntimeException;

/**
 * Направление не найдено.
 *
 * Бросается и для несуществующего id, и для направления другого пользователя:
 * снаружи оба случая неразличимы (404), иначе API раскрывал бы наличие чужих
 * записей. Перевод в HTTP-ответ — на границе приложения (bootstrap/app.php).
 */
final class DirectionNotFound extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Направление %d не найдено.', $id));
    }
}
