<?php

namespace App\Modules\Task\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Task\Application\Queries\ListTasks\ListTasksQuery;
use App\Modules\Task\Application\Results\TaskResult;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Infrastructure\Http\Requests\IndexTaskRequest;
use App\Modules\Task\Infrastructure\Http\Resources\TaskResource;
use App\Modules\User\Domain\User;
use App\Shared\Application\Bus\QueryBus;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Список карточек текущего пользователя с фильтрами доски.
 *
 * Фильтры валидируются IndexTaskRequest: неизвестный тип, колонка или
 * нечисловой идентификатор дают 422, а не тихо отключённый фильтр.
 */
final class IndexTaskController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
    ) {}

    /**
     * GET /api/v1/tasks?type=&column=&direction_id=&initiative_id=&project_id=
     */
    public function __invoke(IndexTaskRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        /** @var CardType|null $type */
        $type = $request->enumValue('type', CardType::class);

        /** @var CardColumn|null $column */
        $column = $request->enumValue('column', CardColumn::class);

        /** @var list<TaskResult> $tasks */
        $tasks = $this->queries->ask(new ListTasksQuery(
            (int) $user->getKey(),
            $type,
            $column,
            $request->intValue('direction_id'),
            $request->intValue('initiative_id'),
            $request->intValue('project_id'),
        ));

        return TaskResource::collection($tasks);
    }
}
