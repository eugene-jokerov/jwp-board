<?php

namespace App\Modules\User\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Пароль в открытом виде как value object домена User.
 *
 * Политика длины задана здесь, чтобы форма регистрации и доменная логика
 * пользовались одним правилом. Хеширование выполняется на уровне адаптера
 * (каст `hashed` модели), сам объект хеш не хранит.
 */
final readonly class Password
{
    public const int MIN_LENGTH = 8;

    public const int MAX_LENGTH = 255;

    private function __construct(
        private string $value,
    ) {}

    /**
     * Создать пароль с проверкой политики длины.
     *
     * @throws InvalidArgumentException если пароль короче MIN_LENGTH или длиннее MAX_LENGTH
     */
    public static function fromString(string $value): self
    {
        $length = mb_strlen($value);

        if ($length < self::MIN_LENGTH) {
            throw new InvalidArgumentException(sprintf('Пароль должен содержать минимум %d символов.', self::MIN_LENGTH));
        }

        if ($length > self::MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf('Пароль не может быть длиннее %d символов.', self::MAX_LENGTH));
        }

        return new self($value);
    }

    /**
     * Пароль в открытом виде: только для передачи в хешер.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Не выводим пароль в дампах, логах и исключениях.
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }
}
