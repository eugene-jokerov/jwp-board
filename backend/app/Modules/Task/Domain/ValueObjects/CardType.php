<?php

namespace App\Modules\Task\Domain\ValueObjects;

/**
 * Тип карточки — имя узла в иерархии доски.
 *
 * Инициатива живёт на стратегическом уровне, проект — на тактическом,
 * задача — на операционном. Тип неизменяем: карточка не переезжает между
 * уровнями, меняется только её колонка.
 */
enum CardType: string
{
    case Initiative = 'initiative';
    case Project = 'project';
    case Task = 'task';

    /**
     * Уровень доски, к которому относится тип карточки.
     */
    public function level(): CardLevel
    {
        return match ($this) {
            self::Initiative => CardLevel::Strategic,
            self::Project => CardLevel::Tactical,
            self::Task => CardLevel::Operational,
        };
    }

    /**
     * Подпись типа для сообщений об ошибках.
     */
    public function label(): string
    {
        return match ($this) {
            self::Initiative => 'инициатива',
            self::Project => 'проект',
            self::Task => 'задача',
        };
    }
}
