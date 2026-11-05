<?php

namespace App\Modules\Task\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Task\Application\Queries\ListTasks\ListTasksQuery;
use App\Modules\Task\Application\Results\TaskResult;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Infrastructure\Http\Resources\TaskResource;
use App\Modules\User\Domain\User;
use App\Shared\Application\Bus\QueryBus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Список карточек текущего пользователя с фильтрами доски.
 */
final class IndexTaskController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
    ) {}

    /**
     * GET /api/v1/tasks?type=&column=&direction_id=&initiative_id=&project_id=
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $type = $request->query('type');
        $column = $request->query('column');

        /** @var list<TaskResult> $tasks */
        $tasks = $this->queries->ask(new ListTasksQuery(
            (int) $user->getKey(),
            is_string($type) ? CardType::tryFrom($type) : null,
            is_string($column) ? CardColumn::tryFrom($column) : null,
            $this->intOrNull($request->query('direction_id')),
            $this->intOrNull($request->query('initiative_id')),
            $this->intOrNull($request->query('project_id')),
        ));

        return TaskResource::collection($tasks);
    }

    /**
     * Числовой фильтр из query-строки; всё, что не число, игнорируется.
     */
    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
