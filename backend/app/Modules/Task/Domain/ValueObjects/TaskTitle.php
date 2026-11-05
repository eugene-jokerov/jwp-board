<?php

namespace App\Modules\Task\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Название карточки.
 *
 * Уникальность не проверяется: одинаковые названия задач — норма.
 */
final readonly class TaskTitle
{
    public const int MAX_LENGTH = 255;

    private function __construct(
        private string $value,
    ) {}

    /**
     * @throws InvalidArgumentException если название пустое или слишком длинное
     */
    public static function fromString(string $value): self
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidArgumentException('Название карточки не может быть пустым.');
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Название карточки не может быть длиннее 255 символов.');
        }

        return new self($normalized);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
