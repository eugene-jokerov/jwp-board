<?php

namespace App\Modules\Task\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Task\Application\Commands\CreateTask\CreateTaskCommand;
use App\Modules\Task\Application\Results\TaskResult;
use App\Modules\Task\Infrastructure\Http\Requests\StoreTaskRequest;
use App\Modules\Task\Infrastructure\Http\Resources\TaskResource;
use App\Shared\Application\Bus\CommandBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Создание карточки на любом уровне доски.
 */
final class StoreTaskController extends Controller
{
    public function __construct(
        private readonly CommandBus $bus,
    ) {}

    /**
     * POST /api/v1/tasks
     */
    public function __invoke(StoreTaskRequest $request): JsonResponse
    {
        /** @var TaskResult $result */
        $result = $this->bus->dispatch(
            CreateTaskCommand::fromArray((int) $request->user()->getKey(), $request->toCommandPayload()),
        );

        return (new TaskResource($result))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
