<?php

namespace App\Modules\Direction\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Direction\Application\Commands\DeleteDirection\DeleteDirectionCommand;
use App\Shared\Application\Bus\CommandBus;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Удаление направления.
 *
 * Направление с карточками не удаляется: доменное исключение DirectionHasCards
 * переводится в 409 на границе приложения.
 */
final class DestroyDirectionController extends Controller
{
    public function __construct(
        private readonly CommandBus $bus,
    ) {}

    /**
     * DELETE /api/v1/directions/{direction}
     */
    public function __invoke(Request $request, int $direction): Response
    {

        $this->bus->dispatch(new DeleteDirectionCommand((int) $request->user()->getKey(), $direction));

        return response()->noContent();
    }
}
