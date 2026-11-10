<?php

namespace App\Modules\Task\Domain\ValueObjects;

/**
 * Приоритет карточки.
 *
 * Набор не зависит ни от уровня, ни от колонки: приоритет — свойство карточки,
 * как тема, а не этап работы. Поэтому правило «только беклог», которое есть
 * у степени проработки (CardColumn::acceptsIdeas), здесь не нужно: приоритет
 * задаётся на любом уровне и хранится при любом переходе между колонками.
 *
 * Значение по умолчанию — средний: карточка без явно выбранного приоритета
 * не должна выглядеть ни срочной, ни маловажной.
 */
enum CardPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    /**
     * Приоритет по умолчанию: его получает карточка, созданная без выбора.
     */
    public static function default(): self
    {
        return self::Medium;
    }

    /**
     * Подпись приоритета — для интерфейса и сообщений об ошибках.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Низкий',
            self::Medium => 'Средний',
            self::High => 'Высокий',
        };
    }

    /**
     * Все приоритеты от низкого к высокому: в этом порядке их показывает форма.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::Low, self::Medium, self::High];
    }
}
