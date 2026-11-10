<?php

namespace App\Modules\Task\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Описание карточки.
 *
 * Лимит 2000 символов — техническое ограничение поля ввода, а не бизнес-правило;
 * в журнал изменений значение не пишется, чтобы не таскать большие тексты
 * в истории. В списке доски описание не отдаётся: оно приходит только
 * в детальном просмотре карточки (`GET /tasks/{task}`).
 */
final readonly class TaskDescription
{
    public const int MAX_LENGTH = 2000;

    private function __construct(
        private ?string $value,
    ) {}

    /**
     * Описание из внешнего источника; пустая строка нормализуется в null.
     *
     * @throws InvalidArgumentException если текст длиннее лимита
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
                'Описание карточки не может быть длиннее %d символов.',
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
