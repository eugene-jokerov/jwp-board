<?php

namespace App\Modules\History\Domain\ValueObjects;

/**
 * Тип сущности в журнале изменений.
 *
 * Список закрыт: журнал ведётся только для карточек и направлений. В базе
 * хранится строковый код (`direction`, `task`), а не имя класса — запись
 * должна пережить переименование класса или разделение модуля.
 */
enum EntityType: string
{
    case Direction = 'direction';
    case Task = 'task';

    /**
     * Понятное имя типа для ответов API и подсказок.
     */
    public function label(): string
    {
        return match ($this) {
            self::Direction => 'Направление',
            self::Task => 'Карточка',
        };
    }
}
