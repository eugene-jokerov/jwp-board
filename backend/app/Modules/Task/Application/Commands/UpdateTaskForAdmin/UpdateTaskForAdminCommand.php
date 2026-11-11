<?php

namespace App\Modules\Task\Application\Commands\UpdateTaskForAdmin;

use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardPriority;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;

/**
 * Команда изменения карточки из админки.
 *
 * Набор полей совпадает с UpdateTaskCommand: отсутствие ключа означает
 * «не менять», смена колонки идёт тем же путём. Отличие одно — владелец
 * приходит из формы, поэтому карточка ищется в его границах.
 */
final readonly class UpdateTaskForAdminCommand
{
    /**
     * @param  array{direction_id?: int|null, initiative_id?: int|null, project_id?: int|null}  $links  только присутствующие в форме ссылки
     * @param  array{title?: TaskTitle|null, description?: string|null, slug?: string|null, deadline?: string|null, column?: CardColumn|null, priority?: CardPriority|null, idea?: bool}  $changes
     */
    public function __construct(
        public int $ownerId,
        public int $taskId,
        public array $links = [],
        public array $changes = [],
    ) {}
}
