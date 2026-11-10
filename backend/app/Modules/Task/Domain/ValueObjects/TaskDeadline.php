<?php

namespace App\Modules\Task\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Дедлайн карточки.
 *
 * Необязательная дата окончания: в интерфейсе её выбирают календарём, поэтому
 * дата обязательна, а время необязательно и по умолчанию — начало дня
 * (`00:00:00`). Пустая строка нормализуется в null: так форма сообщает
 * «дедлайн убрали», не зная, что сервер хранит null.
 *
 * Значение хранится строкой в формате `Y-m-d H:i:s`, а не immutable-объектом:
 * прикладной слой передаёт его в репозиторий как атрибут Eloquent. Часовой
 * пояс не пересчитывается — дата из календаря означает именно тот день,
 * который выбрал пользователь (см. config/app.php, timezone).
 */
final readonly class TaskDeadline
{
    /**
     * Признак строки даты у поля ввода: дата и, опционально, время.
     *
     * Проверка стоит перед разбором даты, чтобы «14:30» (время без даты)
     * и ISO-время вида `T12:00:00` не превращались в сегодняшнюю дату.
     */
    private const string PATTERN = '/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/';

    private function __construct(
        private ?string $value,
    ) {}

    /**
     * Дедлайн из внешнего источника; пустая строка нормализуется в null.
     *
     * @throws InvalidArgumentException если дата не распознана
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

        if (preg_match(self::PATTERN, $normalized) !== 1) {
            throw new InvalidArgumentException('Неверная дата дедлайна.');
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', self::normalize($normalized));

        // createFromFormat прощает переполнение (30 февраля → 2 марта),
        // поэтому распознанная дата сверяется с исходной строкой.
        if ($date === false || $date->format('Y-m-d H:i:s') !== self::normalize($normalized)) {
            throw new InvalidArgumentException('Неверная дата дедлайна.');
        }

        return new self($date->format('Y-m-d H:i:s'));
    }

    /**
     * Дата с началом дня: время не передано — дедлайн на весь выбранный день.
     */
    private static function normalize(string $value): string
    {
        return match (mb_strlen($value)) {
            10 => $value.' 00:00:00',
            16 => $value.':00',
            default => str_replace('T', ' ', $value),
        };
    }

    public function value(): ?string
    {
        return $this->value;
    }
}
