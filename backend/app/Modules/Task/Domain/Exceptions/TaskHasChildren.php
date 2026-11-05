<?php

namespace App\Modules\Task\Domain\Exceptions;

use RuntimeException;

/**
 * Карточку нельзя удалить: на неё ссылаются дочерние карточки.
 *
 * У инициативы это проекты (`initiative_id`), у проекта — задачи (`project_id`).
 * Правило защищает иерархию: без родителя дочерние карточки потеряли бы
 * направление и место на доске. На границе приложения — 409 Conflict.
 */
final class TaskHasChildren extends RuntimeException
{
    public static function withChildren(int $taskId, int $children): self
    {
        return new self(sprintf(
            'У карточки есть дочерние карточки (%d). Удаление запрещено: сначала удалите их.',
            $children,
        ));
    }
}
