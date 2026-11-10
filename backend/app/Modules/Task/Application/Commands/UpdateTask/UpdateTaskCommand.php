<?php

namespace App\Modules\Task\Application\Commands\UpdateTask;

use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;

/**
 * Команда изменения карточки.
 *
 * Все поля необязательны, отсутствие поля означает «не менять». Смена колонки
 * идёт через это же PATCH (поле column) — отдельного эндпоинта перехода нет.
 *
 * Ссылки иерархии передаются только теми ключами, которые пришли в запросе:
 * отсутствующий ключ = «оставить текущее значение» (см. TaskEditor::mergeLinks).
 * Явный null = «отвязать от родителя»; для карточки с детьми это запрещено.
 */
final readonly class UpdateTaskCommand
{
    /**
     * @param  array{direction_id?: int|null, initiative_id?: int|null, project_id?: int|null}  $links  только присутствующие в запросе ссылки
     * @param  array{title?: TaskTitle|null, description?: string|null, deadline?: string|null, column?: CardColumn|null}  $changes
     */
    public function __construct(
        public int $userId,
        public int $taskId,
        public array $links = [],
        public array $changes = [],
    ) {}
}
