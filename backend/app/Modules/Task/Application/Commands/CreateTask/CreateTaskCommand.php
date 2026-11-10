<?php

namespace App\Modules\Task\Application\Commands\CreateTask;

use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Domain\ValueObjects\TaskDeadline;
use App\Modules\Task\Domain\ValueObjects\TaskDescription;
use App\Modules\Task\Domain\ValueObjects\TaskSlug;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;

/**
 * Команда создания карточки.
 *
 * Тип и колонка обязательны: тип задаёт уровень иерархии (и значит, какие
 * ссылки допустимы), колонка — место на доске. Дедлайн и кодовое название
 * (slug) необязательны: карточка может жить без срока и без метки.
 * `idea` — степень проработки: идея или готова к реализации. Флаг имеет смысл
 * только в беклоге, поэтому вне его создание сбрасывает значение в `false`.
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
        public ?TaskDeadline $deadline = null,
        public ?TaskSlug $slug = null,
        public bool $idea = false,
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
            array_key_exists('deadline', $payload)
                ? TaskDeadline::fromNullableString($payload['deadline'] === null ? null : (string) $payload['deadline'])
                : null,
            array_key_exists('slug', $payload)
                ? TaskSlug::fromNullableString($payload['slug'] === null ? null : (string) $payload['slug'])
                : null,
            ($payload['idea'] ?? false) === true,
        );
    }
}
