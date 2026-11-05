<?php

namespace App\Modules\Task\Domain\Exceptions;

use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardLevel;
use RuntimeException;

/**
 * Смену колонки блокируют дочерние карточки в работе.
 *
 * Правило уровней: пока у тактической карточки (проекта) есть задачи в
 * «В работе» или у стратегической (инициативы) — проекты в «В работе»,
 * она заперта в текущей колонке. Иначе «в работе» остались бы карточки
 * без родителя, взятого в работу.
 */
final class ColumnChangeBlocked extends RuntimeException
{
    public static function byChildren(int $taskId, CardLevel $level, CardColumn $from, CardColumn $to, int $children): self
    {
        return new self(sprintf(
            'Нельзя сменить колонку с «%s» на «%s»: на %s уровне есть дочерние карточки в работе (%d). '
            .'Сначала завершите или отмените их.',
            $from->label(),
            $to->label(),
            $level->label(),
            $children,
        ));
    }
}
