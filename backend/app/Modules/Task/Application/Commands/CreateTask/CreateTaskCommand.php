<?php

namespace App\Modules\Task\Application\Commands\CreateTask;

use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Domain\ValueObjects\TaskDescription;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;

/**
 * Команда создания карточки.
 *
 * Тип и колонка обязательны: тип задаёт уровень иерархии (и значит, какие
 * ссылки допустимы), колонка — место на доске.
 */
final readonly class CreateTaskCommand
{
    public function __construct(
        public int $userId,
        public CardType $type,
        public CardColumn $column,
        public TaskTitle $title,
        public ?TaskDescription $description = null,
        public ?int $directionId = null,
        public ?int $initiativeId = null,
        public ?int $projectId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(int $userId, array $payload): self
    {
        return new self(
            $userId,
            CardType::from((string) $payload['type']),
            CardColumn::from((string) $payload['column']),
            TaskTitle::fromString((string) $payload['title']),
            TaskDescription::fromNullableString(isset($payload['description']) ? (string) $payload['description'] : null),
            isset($payload['direction_id']) ? (int) $payload['direction_id'] : null,
            isset($payload['initiative_id']) ? (int) $payload['initiative_id'] : null,
            isset($payload['project_id']) ? (int) $payload['project_id'] : null,
        );
    }
}
