<?php

namespace App\Modules\Task\Application\Queries\ListTasks;

use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Task\Application\Results\ListTasksResult;
use App\Modules\Task\Application\Results\TaskResult;
use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Domain\Services\ColumnTransitionPolicy;
use App\Modules\Task\Domain\ValueObjects\CardColumn;

/**
 * Хендлер запроса списка карточек.
 *
 * Каждая карточка отдаётся вместе с признаками, которые нужны доске:
 * цветом направления и списком колонок, доступных для перехода.
 *
 * Два режима выборки:
 *  - первичная загрузка доски (`initial`): открытые колонки (Беклог, В работе)
 *    отдаются целиком, закрытые (Готово, Отмена) — порцией
 *    CardColumn::CLOSED_CARDS_PER_LEVEL на каждую пару «уровень + колонка»;
 *    клиент догружает остальное страницами;
 *  - страница/фильтр (`limit`/`offset`): отдаётся ровно запрошенный кусок,
 *    ограниченный фильтрами (обычно `type` + `column`, то есть та же пара).
 * Счётчики в обоих режимах считаются по фильтрам, а не по отданной выборке:
 * клиенту нужно полное число карточек пары, чтобы показать «показать ещё».
 */
final readonly class ListTasksQueryHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private ColumnTransitionPolicy $policy,
        private DirectionRepository $directions,
    ) {}

    public function handle(ListTasksQuery $query): ListTasksResult
    {
        $filters = [
            'type' => $query->type,
            'column' => $query->column,
            'direction_id' => $query->directionId,
            'initiative_id' => $query->initiativeId,
            'project_id' => $query->projectId,
        ];

        // Первичная загрузка — только когда клиент не выбрал колонку: явный
        // фильтр по колонке всегда означает «нужна эта колонка целиком или страница».
        $initial = $query->initial && $query->column === null;

        if ($initial) {
            $tasks = $this->tasks->listInitialForUser(
                $query->userId,
                $filters,
                CardColumn::CLOSED_CARDS_PER_LEVEL,
            );
            $total = $this->tasks->countForUser($query->userId, $filters);
        } else {
            $tasks = $this->tasks->listForUser($query->userId, [
                ...$filters,
                'order' => $query->order,
                'limit' => $query->limit,
                'offset' => $query->offset,
            ]);

            // Общее число нужно только странице: без `limit` клиент и так видит
            // весь список, а лишний COUNT(*) на главном запросе доски не нужен.
            $total = $query->limit === null ? count($tasks) : $this->tasks->countForUser($query->userId, $filters);
        }

        $closedByLevel = $this->tasks->countClosedByLevelForUser($query->userId, $filters);
        $totalByColumn = $this->tasks->countByColumnForUser($query->userId, $filters);

        $colors = $this->colors($query->userId);
        $result = [];

        // Доступные переходы считаются пачкой: один запрос на весь список,
        // а не count() на каждую карточку.
        foreach ($this->policy->allowedTransitionsFor($tasks) as $row) {
            $task = $row['task'];

            $result[] = TaskResult::fromTask(
                $task,
                $row['transitions'],
                $task->direction_id === null ? null : ($colors[$task->direction_id] ?? null),
            );
        }

        return new ListTasksResult($result, $total, $totalByColumn, $closedByLevel);
    }

    /**
     * Цвета направлений пользователя по id — один запрос на весь список.
     *
     * @return array<int, DirectionColor>
     */
    private function colors(int $userId): array
    {
        $colors = [];

        foreach ($this->directions->allForUser($userId) as $direction) {
            $colors[(int) $direction->id] = $direction->color;
        }

        return $colors;
    }
}
