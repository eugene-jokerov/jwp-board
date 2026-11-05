<?php

namespace App\Modules\Task\Application\Commands\DeleteTask;

use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Domain\Exceptions\TaskHasChildren;
use App\Modules\Task\Domain\Services\ColumnTransitionPolicy;

/**
 * Хендлер команды удаления карточки.
 *
 * Удаление карточки с детьми запрещено доменным правилом: сначала удаляются
 * дочерние карточки. Проверка отдана политике уровней — там же, где правило
 * смены колонки, потому что оба правила опираются на одни и те же дети.
 */
final readonly class DeleteTaskCommandHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private ColumnTransitionPolicy $policy,
    ) {}

    /**
     * @throws TaskHasChildren если на карточку ссылаются дочерние карточки
     */
    public function handle(DeleteTaskCommand $command): void
    {
        $task = $this->tasks->findForUser($command->userId, $command->taskId);

        $this->policy->ensureCanDelete($task);

        $this->tasks->delete($task);
    }
}
