<?php

namespace App\Modules\User\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Email как value object домена User.
 *
 * Хранит адрес в нормализованном виде (без пробелов, в нижнем регистре):
 * так регистрация и вход не зависят от регистра символов, а сравнение адресов
 * сводится к сравнению строк.
 */
final readonly class Email
{
    private const int MAX_LENGTH = 255;

    private function __construct(
        private string $value,
    ) {}

    /**
     * Создать email из произвольной строки.
     *
     * @throws InvalidArgumentException если адрес пустой, слишком длинный или некорректный
     */
    public static function fromString(string $value): self
    {
        $normalized = mb_strtolower(trim($value));

        if ($normalized === '') {
            throw new InvalidArgumentException('Email не может быть пустым.');
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Email слишком длинный.');
        }

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Email имеет некорректный формат.');
        }

        return new self($normalized);
    }

    /**
     * Нормализованное представление адреса.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Сравнение адресов (регистр уже приведён в fromString()).
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
