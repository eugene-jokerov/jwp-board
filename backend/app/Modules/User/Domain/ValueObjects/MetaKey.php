<?php

namespace App\Modules\User\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Ключ пользовательской настройки.
 *
 * Ключ — служебный идентификатор настройки в пространстве самого приложения,
 * а не пользовательский текст, поэтому набор символов ограничен: строчные
 * латинские буквы, цифры и подчёркивания, сегменты разделяются точкой
 * (`board.collapsed_levels`). Точка даёт плоское пространство имён без
 * вложенных структур: по префиксу видно, к какому разделу относится настройка.
 */
final readonly class MetaKey
{
    /**
     * Предел длины ключа — совпадает с колонкой `meta_key` (varchar 255).
     */
    public const int MAX_LENGTH = 255;

    /**
     * Допустимая форма ключа: сегменты из [a-z0-9_], разделённые точкой.
     */
    private const string PATTERN = '/^[a-z0-9_]+(\.[a-z0-9_]+)*$/';

    private function __construct(
        private string $value,
    ) {}

    /**
     * @throws InvalidArgumentException если ключ пустой, длинный или неверной формы
     */
    public static function fromString(string $value): self
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidArgumentException('Ключ настройки не может быть пустым.');
        }

        if (strlen($normalized) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Ключ настройки не может быть длиннее 255 символов.');
        }

        if (preg_match(self::PATTERN, $normalized) !== 1) {
            throw new InvalidArgumentException(
                'Ключ настройки может содержать только строчные латинские буквы, цифры, подчёркивания и точки.',
            );
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
