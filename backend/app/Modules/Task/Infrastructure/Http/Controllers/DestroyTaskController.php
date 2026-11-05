<?php

namespace App\Modules\Task\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Task\Application\Commands\DeleteTask\DeleteTaskCommand;
use App\Modules\User\Domain\User;
use App\Shared\Application\Bus\CommandBus;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Удаление карточки.
 *
 * Карточка с дочерними не удаляется: доменное исключение TaskHasChildren
 * переводится в 409 на границе приложения.
 */
final class DestroyTaskController extends Controller
{
    public function __construct(
        private readonly CommandBus $bus,
    ) {}

    /**
     * DELETE /api/v1/tasks/{task}
     */
    public function __invoke(Request $request, int $task): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->bus->dispatch(new DeleteTaskCommand((int) $user->getKey(), $task));

        return response()->noContent();
    }
}
