<?php

namespace App\Modules\Direction\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Direction\Application\Commands\CreateDirection\CreateDirectionCommand;
use App\Modules\Direction\Application\Results\DirectionResult;
use App\Modules\Direction\Infrastructure\Http\Requests\StoreDirectionRequest;
use App\Modules\Direction\Infrastructure\Http\Resources\DirectionResource;
use App\Modules\User\Domain\User;
use App\Shared\Application\Bus\CommandBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Создание направления.
 */
final class StoreDirectionController extends Controller
{
    public function __construct(
        private readonly CommandBus $bus,
    ) {}

    /**
     * POST /api/v1/directions
     */
    public function __invoke(StoreDirectionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var DirectionResult $result */
        $result = $this->bus->dispatch(
            CreateDirectionCommand::fromArray((int) $user->getKey(), $request->toCommandPayload()),
        );

        return (new DirectionResource($result))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
