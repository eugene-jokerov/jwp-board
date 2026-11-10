<?php

namespace App\Modules\Direction\Infrastructure\Persistence;

use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;
use App\Modules\History\Domain\Contracts\HistoryRecorder;
use App\Modules\History\Domain\HistoryEntry;
use App\Modules\History\Domain\ValueObjects\EntityType;

/**
 * Декоратор репозитория направлений, который ведёт журнал изменений.
 *
 * История пишется здесь, а не в хендлерах команд: любой путь изменения
 * направления проходит через порт, поэтому «изменение без записи» невозможно.
 * Декоратор вызывается внутри транзакции команды (TransactionMiddleware),
 * значит изменения сущности и записи журнала атомарны.
 */
final readonly class HistoryRecordingDirectionRepository implements DirectionRepository
{
    public function __construct(
        private DirectionRepository $inner,
        private HistoryRecorder $history,
    ) {}

    public function findForUser(int $userId, int $id): Direction
    {
        return $this->inner->findForUser($userId, $id);
    }

    public function allForUser(int $userId): array
    {
        return $this->inner->allForUser($userId);
    }

    public function countForUser(int $userId): int
    {
        return $this->inner->countForUser($userId);
    }

    public function create(int $userId, DirectionTitle $title, DirectionColor $color): Direction
    {
        $direction = $this->inner->create($userId, $title, $color);

        $this->history->record(
            HistoryEntry::inserted(EntityType::Direction, (int) $direction->id, $userId),
        );

        return $direction;
    }

    public function update(Direction $direction, ?DirectionTitle $title, ?DirectionColor $color): Direction
    {
        $userId = $direction->user_id;
        $entityId = (int) $direction->id;

        // Снимок «до» нужен для old_value: каст enum отдаёт уже доменный тип,
        // в журнал кладём строковый код — так запись не зависит от названия case.
        $old = [
            'title' => $direction->title,
            'color' => $direction->color->value,
        ];

        $updated = $this->inner->update($direction, $title, $color);

        $new = [
            'title' => $updated->title,
            'color' => $updated->color->value,
        ];

        $entries = [];

        foreach ($new as $field => $value) {
            if ($old[$field] === $value) {
                continue;
            }

            $entries[] = HistoryEntry::changed(EntityType::Direction, $entityId, $userId, $field, $old[$field], $value);
        }

        $this->history->recordMany($entries);

        return $updated;
    }

    public function delete(Direction $direction): void
    {
        // Запись об удалении делается до самого удаления: если DELETE упадёт,
        // транзакция откатится вместе с журналом.
        $this->history->record(
            HistoryEntry::deleted(EntityType::Direction, (int) $direction->id, $direction->user_id),
        );

        $this->inner->delete($direction);
    }

    public function countCards(Direction $direction): int
    {
        return $this->inner->countCards($direction);
    }
}
