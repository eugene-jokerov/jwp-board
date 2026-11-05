<?php

namespace App\Modules\Direction\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Палитра цветов направления.
 *
 * Цвет — доменное ограничение, а не оформление: выбор замкнут фиксированным
 * списком, поэтому карточки одного направления выглядят одинаково на всех
 * уровнях доски. В базе хранится ключ палитры (`c1`…`c19`), а HEX-оттенок —
 * дело клиента: перекрасить палитру можно, не мигрируя сохранённые данные.
 *
 * Служебного нейтрального цвета `c0` здесь нет: он закреплён за карточками
 * без направления и направлению выбран быть не может.
 */
enum DirectionColor: string
{
    case Blue = 'c1';
    case Amber = 'c2';
    case Green = 'c3';
    case Violet = 'c4';
    case Teal = 'c5';
    case Pink = 'c6';
    case Lime = 'c7';
    case Indigo = 'c8';
    case Orange = 'c9';
    case Emerald = 'c10';
    case Fuchsia = 'c11';
    case Sky = 'c12';
    case Yellow = 'c13';
    case Slate = 'c14';
    case Purple = 'c15';
    case Aqua = 'c16';
    case Copper = 'c17';
    case Cyan = 'c18';
    case Crimson = 'c19';

    /**
     * Создать цвет по ключу палитры из внешнего источника.
     *
     * @throws InvalidArgumentException если ключа нет в палитре
     */
    public static function fromKey(string $key): self
    {
        $color = self::tryFrom(trim($key));

        if ($color === null) {
            throw new InvalidArgumentException(sprintf('Неизвестный цвет направления: %s.', $key));
        }

        return $color;
    }

    /**
     * Ключи палитры — для правила валидации (Rule::enum) и ответов API.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(static fn (self $color): string => $color->value, self::cases());
    }

    /**
     * Ключ палитры, как он хранится в базе.
     */
    public function key(): string
    {
        return $this->value;
    }
}
