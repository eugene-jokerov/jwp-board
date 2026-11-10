<?php

namespace App\Modules\Task\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Кодовое название карточки (slug).
 *
 * Необязательная короткая метка: пустая строка нормализуется в null, так форма
 * сообщает «кодовое название стёрли». Ограничений по символам нет и
 * уникальность не проверяется — одинаковые метки у разных карточек допустимы.
 * Лимит длины дублирует колонку `slug` в таблице tasks.
 */
final readonly class TaskSlug
{
    public const int MAX_LENGTH = 16;

    private function __construct(
        private ?string $value,
    ) {}

    /**
     * Кодовое название из внешнего источника; пустая строка нормализуется в null.
     *
     * @throws InvalidArgumentException если значение длиннее лимита
     */
    public static function fromNullableString(?string $value): self
    {
        if ($value === null) {
            return new self(null);
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return new self(null);
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Кодовое название карточки не может быть длиннее %d символов.',
                self::MAX_LENGTH,
            ));
        }

        return new self($normalized);
    }

    public function value(): ?string
    {
        return $this->value;
    }
}
