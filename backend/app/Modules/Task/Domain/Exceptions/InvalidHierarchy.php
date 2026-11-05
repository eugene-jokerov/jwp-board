<?php

namespace App\Modules\Task\Domain\Exceptions;

use App\Modules\Task\Domain\ValueObjects\CardType;
use InvalidArgumentException;

/**
 * Нарушение иерархии карточки.
 *
 * Защищает денормализацию: `direction_id` карточки обязан совпадать с
 * направлением её инициативы, `initiative_id` — с инициативой её проекта,
 * а уровень карточки должен соответствовать её типу. Наследует
 * InvalidArgumentException: по смыслу это некорректные входные данные (422).
 */
final class InvalidHierarchy extends InvalidArgumentException
{
    /**
     * Стратегический уровень: у инициативы обязано быть своё направление.
     */
    public static function directionRequired(CardType $type): self
    {
        return new self(sprintf('Для карточки типа «%s» нужно указать направление.', $type->label()));
    }

    /**
     * Направление не должно задаваться напрямую: оно наследуется от родителя.
     */
    public static function directionIsInherited(CardType $type): self
    {
        return new self(sprintf(
            'Для карточки типа «%s» направление не указывается: оно наследуется от родительской карточки.',
            $type->label(),
        ));
    }

    /**
     * Родительская карточка не того типа.
     */
    public static function wrongParentType(CardType $expected, CardType $actual): self
    {
        return new self(sprintf(
            'Родительская карточка должна быть типа «%s», а указана «%s».',
            $expected->label(),
            $actual->label(),
        ));
    }

    /**
     * Денормализованное поле не совпадает с родителем.
     */
    public static function fieldMismatch(string $field, ?int $expected, ?int $actual): self
    {
        return new self(sprintf(
            'Поле %s не совпадает с родительской карточкой: ожидалось %s, передано %s.',
            $field,
            $expected === null ? 'null' : (string) $expected,
            $actual === null ? 'null' : (string) $actual,
        ));
    }

    /**
     * Родительской карточки нет или она чужая.
     */
    public static function parentNotFound(string $field, int $id): self
    {
        return new self(sprintf('Родительская карточка %s = %d не найдена.', $field, $id));
    }

    /**
     * Ссылка карточки сама на себя.
     */
    public static function selfReference(int $id): self
    {
        return new self(sprintf('Карточка %d не может быть родителем самой себе.', $id));
    }

    /**
     * Поле задано на уровне, где не имеет смысла.
     */
    public static function fieldNotAllowed(CardType $type, string $field): self
    {
        return new self(sprintf(
            'Для карточки типа «%s» поле %s быть не может: оно всегда null.',
            $type->label(),
            $field,
        ));
    }
}
