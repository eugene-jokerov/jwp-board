<?php

namespace App\Modules\Task\Application\Queries\ListTasks;

use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;

/**
 * Запрос списка карточек пользователя.
 *
 * Фильтры необязательны: доска запрашивает уровень (type), колонку и родителя,
 * а клиент может уточнить выборку по direction_id.
 */
final readonly class ListTasksQuery
{
    public function __construct(
        public int $userId,
        public ?CardType $type = null,
        public ?CardColumn $column = null,
        public ?int $directionId = null,
        public ?int $iniciativeId = null,
        public ?int $projectId = null,
    ) {}
}
