<?php

namespace App\Modules\Task\Domain\ValueObjects;

/**
 * Колонки канбан-доски.
 *
 * Набор одинаков на всех уровнях: инициативы, проекты и задачи раскладываются
 * по одним и тем же колонкам, меняется только правило перехода между ними.
 * `complete` — «Готово», `canceled` — «Отмена» (американское написание через
 * одну «l»).
 */
enum CardColumn: string
{
    case Backlog = 'backlog';
    case InProgress = 'in_progress';
    case Complete = 'complete';
    case Canceled = 'canceled';

    /**
     * Карточка взята в работу.
     */
    public function isInProgress(): bool
    {
        return $this === self::InProgress;
    }

    /**
     * Подпись колонки для интерфейса и сообщений об ошибках.
     */
    public function label(): string
    {
        return match ($this) {
            self::Backlog => 'Беклог',
            self::InProgress => 'В работе',
            self::Complete => 'Готово',
            self::Canceled => 'Отмена',
        };
    }

    /**
     * Все колонки в порядке доски слева направо.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::Backlog, self::InProgress, self::Complete, self::Canceled];
    }
}
