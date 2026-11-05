<?php

namespace App\Modules\Task\Domain\Services;

use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Domain\Exceptions\ColumnChangeBlocked;
use App\Modules\Task\Domain\Exceptions\TaskHasChildren;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardLevel;

/**
 * Доменный сервис смены колонки.
 *
 * Правила по уровням доски:
 *  - операционный: любую карточку можно перевести в любую колонку;
 *  - тактический: пока у карточки есть задачи в работе, колонку менять нельзя;
 *  - стратегический: пока у инициативы есть проекты в работе, колонку менять нельзя.
 *
 * Смысл правила: в работе не может быть карточки, у которой родитель уже
 * вышел из работы. Ограничение задано уровнем карточки.
 */
final readonly class ColumnTransitionPolicy
{
    public function __construct(
        private TaskRepository $tasks,
    ) {}

    /**
     * Разрешена ли смена колонки.
     *
     * Переход в ту же колонку — не смена статуса, он разрешён всегда.
     */
    public function isAllowed(Task $task, CardColumn $target): bool
    {
        if ($task->column === $target) {
            return true;
        }

        return $this->blockingChildren($task) === 0;
    }

    /**
     * Колонки, в которые карточку можно перевести прямо сейчас.
     *
     * Именно этот список отдаётся клиенту: интерфейс показывает по нему
     * доступные переходы, не дублируя доменное правило на клиенте.
     *
     * @return list<CardColumn>
     */
    public function allowedTransitions(Task $task): array
    {
        if ($this->blockingChildren($task) > 0) {
            return [$task->column];
        }

        return CardColumn::ordered();
    }

    /**
     * Проверить смену колонки и бросить доменное исключение при запрете.
     *
     * @throws ColumnChangeBlocked если смену блокируют дочерние карточки в работе
     */
    public function ensureCanMove(Task $task, CardColumn $target): void
    {
        if ($task->column === $target) {
            return;
        }

        $children = $this->blockingChildren($task);

        if ($children > 0) {
            throw ColumnChangeBlocked::byChildren((int) $task->id, $task->level(), $task->column, $target, $children);
        }
    }

    /**
     * Запретить удаление карточки, на которую ссылаются дочерние карточки.
     *
     * Удаление блокируют дети в любой колонке: иначе они остались бы без
     * родителя и потеряли бы место на доске.
     *
     * @throws TaskHasChildren если дочерние карточки есть
     */
    public function ensureCanDelete(Task $task): void
    {
        $children = $this->tasks->countChildren($task);

        if ($children > 0) {
            throw TaskHasChildren::withChildren((int) $task->id, $children);
        }
    }

    /**
     * Количество дочерних карточек, которые блокируют смену колонки.
     *
     * На операционном уровне правило не действует: у задачи детей нет.
     */
    private function blockingChildren(Task $task): int
    {
        return match ($task->level()) {
            CardLevel::Operational => 0,
            CardLevel::Tactical, CardLevel::Strategic => $this->tasks->countChildrenInProgress($task),
        };
    }
}
