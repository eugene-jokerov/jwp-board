<?php

namespace App\Modules\Task\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Task\Application\Queries\ShowTask\ShowTaskQuery;
use App\Modules\Task\Application\Results\TaskResult;
use App\Modules\Task\Infrastructure\Http\Resources\TaskResource;
use App\Modules\User\Domain\User;
use App\Shared\Application\Bus\QueryBus;
use Illuminate\Http\Request;

/**
 * Просмотр одной карточки.
 */
final class ShowTaskController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
    ) {}

    /**
     * GET /api/v1/tasks/{task}
     */
    public function __invoke(Request $request, int $task): TaskResource
    {
        /** @var User $user */
        $user = $request->user();

        /** @var TaskResult $result */
        $result = $this->queries->ask(new ShowTaskQuery((int) $user->getKey(), $task));

        return new TaskResource($result);
    }
}
