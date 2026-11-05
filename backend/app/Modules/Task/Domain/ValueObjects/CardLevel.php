<?php

namespace App\Modules\Task\Domain\ValueObjects;

/**
 * Уровень доски, на котором создаётся карточка.
 *
 * Уровень не хранится в базе: он однозначно выводится из типа карточки
 * (см. CardType::level()). Формулировка уровнем используется в описаниях
 * правил, потому что правила перехода между колонками заданы именно уровнями.
 */
enum CardLevel: string
{
    case Strategic = 'strategic';
    case Tactical = 'tactical';
    case Operational = 'operational';

    /**
     * Подпись уровня — для сообщений об ошибках.
     */
    public function label(): string
    {
        return match ($this) {
            self::Strategic => 'стратегическом',
            self::Tactical => 'тактическом',
            self::Operational => 'операционном',
        };
    }
}
