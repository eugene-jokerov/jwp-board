<?php

namespace App\Modules\Task\Application\Results;

use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;

/**
 * Результат сценария над карточкой.
 *
 * Снимок вместо Eloquent-модели: HTTP-адаптер не получает доступ к запросам
 * и сохранению. allowedTransitions — колонки, в которые карточку можно
 * перевести сейчас; их считает доменная политика, а не клиент.
 */
final readonly class TaskResult
{
    /**
     * @param  list<CardColumn>  $allowedTransitions
     */
    public function __construct(
        public int $id,
        public CardType $type,
        public CardColumn $column,
        public string $title,
        public ?string $description,
        public ?int $directionId,
        public ?int $initiativeId,
        public ?int $projectId,
        public ?DirectionColor $directionColor,
        public array $allowedTransitions,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  list<CardColumn>  $allowedTransitions
     */
    public static function fromTask(Task $task, array $allowedTransitions, ?DirectionColor $directionColor = null): self
    {
        return new self(
            (int) $task->id,
            $task->type,
            $task->column,
            $task->title,
            $task->description,
            $task->direction_id,
            $task->initiative_id,
            $task->project_id,
            $directionColor,
            $allowedTransitions,
            $task->created_at?->toIso8601String(),
            $task->updated_at?->toIso8601String(),
        );
    }
}
