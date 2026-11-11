<?php

namespace App\Modules\Task\Application\Commands\DeleteTaskForAdmin;

use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Domain\Exceptions\TaskHasChildren;
use App\Modules\Task\Domain\Services\ColumnTransitionPolicy;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardType;

/**
 * Хендлер команды удаления карточки из админки.
 *
 * Каскадное удаление живёт здесь, а не в DeleteTaskCommandHandler: обычное
 * удаление карточки — пользовательский сценарий с запретом на детей, а
 * вычистка поддерева — право модерации. Журнал истории пишется репозиторием
 * (HistoryRecordingTaskRepository), поэтому записи делает любой из режимов.
 */
final readonly class DeleteTaskForAdminCommandHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private ColumnTransitionPolicy $policy,
    ) {}

    /**
     * Удалить карточку (при `cascade` — вместе с поддеревом).
     *
     * @return int сколько карточек удалено
     *
     * @throws TaskHasChildren если на карточку ссылаются дочерние карточки, а каскад выключен
     */
    public function handle(DeleteTaskForAdminCommand $command): int
    {
        $task = $this->tasks->findForUser($command->ownerId, $command->taskId);

        if (! $command->cascade) {
            $this->policy->ensureCanDelete($task);
            $this->tasks->delete($task);

            return 1;
        }

        $deleted = 1 + $this->countDescendants($task);
        $this->tasks->deleteTree($task);

        return $deleted;
    }

    /**
     * Сколько потомков будет удалено вместе с карточкой.
     *
     * Считается до удаления: после DELETE состав поддерева не восстановить.
     * Рекурсии нет — иерархия трёхуровневая. Тип в фильтре обязателен: у задач
     * проекта денормализован initiative_id, поэтому одно условие по родителю
     * захватило бы и внуков.
     */
    private function countDescendants(Task $task): int
    {
        $userId = (int) $task->user_id;
        $projects = $this->tasks->listForUser($userId, [
            'type' => CardType::Project,
            'initiative_id' => (int) $task->id,
        ]);

        $count = count($projects);

        foreach ($projects as $project) {
            $count += count($this->tasks->listForUser($userId, [
                'type' => CardType::Task,
                'project_id' => (int) $project->id,
            ]));
        }

        // Задачи, привязанные к корню напрямую: у инициативы их не бывает,
        // а у проекта — сколько угодно.
        return $count + count($this->tasks->listForUser($userId, [
            'type' => CardType::Task,
            'project_id' => (int) $task->id,
        ]));
    }
}
