<?php

namespace App\Modules\History\Domain;

use App\Modules\History\Domain\ValueObjects\EntityType;
use App\Modules\History\Domain\ValueObjects\HistoryAction;

/**
 * Одна запись журнала изменений.
 *
 * Журнал неизменяем: запись создаётся один раз и больше не редактируется.
 * Поле, старое и новое значения заполнены только у update; для insert и
 * delete они null.
 */
final readonly class HistoryEntry
{
    public function __construct(
        public EntityType $entityType,
        public int $entityId,
        public int $userId,
        public HistoryAction $action,
        public ?string $field = null,
        public ?string $oldValue = null,
        public ?string $newValue = null,
    ) {}

    /**
     * Запись о создании сущности.
     */
    public static function inserted(EntityType $entityType, int $entityId, int $userId): self
    {
        return new self($entityType, $entityId, $userId, HistoryAction::Insert);
    }

    /**
     * Запись об удалении сущности.
     */
    public static function deleted(EntityType $entityType, int $entityId, int $userId): self
    {
        return new self($entityType, $entityId, $userId, HistoryAction::Delete);
    }

    /**
     * Запись о смене одного поля.
     *
     * Значения передаются уже приведёнными к строке; для крупных полей
     * (description) передаётся null, чтобы не таскать текст в журнале.
     */
    public static function changed(
        EntityType $entityType,
        int $entityId,
        int $userId,
        string $field,
        ?string $oldValue,
        ?string $newValue,
    ): self {
        return new self($entityType, $entityId, $userId, HistoryAction::Update, $field, $oldValue, $newValue);
    }
}
