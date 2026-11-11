<?php

namespace App\Filament\Resources\Tasks\Support;

use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardPriority;
use App\Modules\Task\Domain\ValueObjects\CardType;

/**
 * Варианты значений карточек для фильтров, форм и таблиц админки.
 *
 * Значения приходят из php-enum'ов домена, подписи — те же, что в интерфейсе
 * приложения: у колонки и приоритета их даёт сам enum (label()), тип карточки
 * подписывается здесь — его label() в домене рассчитан на текст ошибок.
 */
final class TaskOptions
{
    /**
     * Типы карточки.
     *
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            CardType::Initiative->value => 'Инициатива',
            CardType::Project->value => 'Проект',
            CardType::Task->value => 'Задача',
        ];
    }

    /**
     * Колонки доски слева направо.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $options = [];

        foreach (CardColumn::ordered() as $column) {
            $options[$column->value] = $column->label();
        }

        return $options;
    }

    /**
     * Приоритеты от низкого к высокому.
     *
     * @return array<string, string>
     */
    public static function priorities(): array
    {
        $options = [];

        foreach (CardPriority::ordered() as $priority) {
            $options[$priority->value] = $priority->label();
        }

        return $options;
    }
}
