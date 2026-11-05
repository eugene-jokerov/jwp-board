<?php

namespace App\Modules\Direction\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Название направления.
 *
 * Уникальность не проверяется: одинаковые названия у разных людей — норма.
 * Value object отвечает только за форму значения: непустое, не длиннее 255
 * символов, без пробелов по краям.
 */
final readonly class DirectionTitle
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
            throw new InvalidArgumentException('Название направления не может быть пустым.');
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Название направления не может быть длиннее 255 символов.');
        }

        return new self($normalized);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
