<?php

namespace App\Modules\History\Domain\ValueObjects;

/**
 * Действие над сущностью в журнале изменений.
 */
enum HistoryAction: string
{
    case Insert = 'insert';
    case Update = 'update';
    case Delete = 'delete';

    /**
     * Заполняются ли в записи поле и его значения.
     *
     * Поле и значения есть только у update: для создания и удаления пишется
     * одна запись без детализации по атрибутам.
     */
    public function hasFieldDetails(): bool
    {
        return $this === self::Update;
    }
}
