<?php

namespace App\Filament\Resources\Directions\Support;

use App\Modules\Direction\Domain\ValueObjects\DirectionColor;

/**
 * Подписи палитры направлений для админки.
 *
 * Цвет — доменное ограничение (DirectionColor), а не оформление: в админке
 * показываем ровно те же ключи и названия, что в мобильном клиенте
 * (mobile/src/board/directionColors.ts), чтобы администратор и пользователь
 * говорили об одном цвете одинаково. Служебный `c0` здесь отсутствует: он
 * закреплён за карточками без направления и направлению не выбирается.
 */
final class DirectionColorOptions
{
    /**
     * Названия цветов палитры по ключу.
     *
     * @var array<string, string>
     */
    private const array LABELS = [
        DirectionColor::Blue->value => 'Синий',
        DirectionColor::Amber->value => 'Янтарный',
        DirectionColor::Green->value => 'Зелёный',
        DirectionColor::Violet->value => 'Фиолетовый',
        DirectionColor::Teal->value => 'Бирюзовый',
        DirectionColor::Pink->value => 'Розовый',
        DirectionColor::Lime->value => 'Лаймовый',
        DirectionColor::Indigo->value => 'Индиго',
        DirectionColor::Orange->value => 'Оранжевый',
        DirectionColor::Emerald->value => 'Изумрудный',
        DirectionColor::Fuchsia->value => 'Фуксия',
        DirectionColor::Sky->value => 'Небесный',
        DirectionColor::Yellow->value => 'Жёлтый',
        DirectionColor::Slate->value => 'Сине-серый',
        DirectionColor::Purple->value => 'Тёмно-лиловый',
        DirectionColor::Aqua->value => 'Морской волны',
        DirectionColor::Copper->value => 'Медный',
        DirectionColor::Cyan->value => 'Глубокий циан',
        DirectionColor::Crimson->value => 'Малиновый',
    ];

    /**
     * Варианты выбора цвета: ключ палитры => подпись.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return self::LABELS;
    }

    /**
     * Подпись цвета по ключу палитры.
     *
     * Неизвестный ключ отдаётся как есть: в базе теоретически может оказаться
     * значение из будущего расширения палитры, и терять его в таблице нельзя.
     */
    public static function label(string|DirectionColor|null $key): ?string
    {
        if ($key instanceof DirectionColor) {
            $key = $key->key();
        }

        if ($key === null) {
            return null;
        }

        return self::LABELS[$key] ?? $key;
    }
}
