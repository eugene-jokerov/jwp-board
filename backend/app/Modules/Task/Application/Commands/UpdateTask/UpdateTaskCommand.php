<?php

namespace App\Modules\Task\Application\Commands\UpdateTask;

use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;

/**
 * Команда изменения карточки.
 *
 * Все поля необязательны, null означает «не менять». Смена колонки идёт
 * через это же PATCH (поле column) — отдельного эндпоинта перехода нет.
 *
 * Ссылки иерархии передаются в том же виде, в каком пришли: если поле есть
 * в запросе, домен перепроверит его на совпадение с родителем, если нет —
 * карточка сохраняет текущие значения.
 */
final readonly class UpdateTaskCommand
{
    /**
     * @param  array{direction_id?: int|null, initiative_id?: int|null, project_id?: int|null}  $links
     * @param  array{title?: TaskTitle|null, description?: string|null, column?: CardColumn|null}  $changes
     */
    public function __construct(
        public int $userId,
        public int $taskId,
        public array $links = [],
        public array $changes = [],
    ) {}
}
