<?php

namespace App\Modules\Direction\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Direction\Application\Commands\UpdateDirection\UpdateDirectionCommand;
use App\Modules\Direction\Application\Results\DirectionResult;
use App\Modules\Direction\Infrastructure\Http\Requests\UpdateDirectionRequest;
use App\Modules\Direction\Infrastructure\Http\Resources\DirectionResource;
use App\Shared\Application\Bus\CommandBus;

/**
 * Изменение направления.
 */
final class UpdateDirectionController extends Controller
{
    public function __construct(
        private readonly CommandBus $bus,
    ) {}

    /**
     * PATCH /api/v1/directions/{direction}
     */
    public function __invoke(UpdateDirectionRequest $request, int $direction): DirectionResource
    {

        /** @var DirectionResult $result */
        $result = $this->bus->dispatch(
            UpdateDirectionCommand::fromArray((int) $request->user()->getKey(), $direction, $request->toCommandPayload()),
        );

        return new DirectionResource($result);
    }
}
