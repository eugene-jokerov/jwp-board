<?php

namespace App\Modules\Direction\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Direction\Application\Queries\ShowDirection\ShowDirectionQuery;
use App\Modules\Direction\Application\Results\DirectionResult;
use App\Modules\Direction\Infrastructure\Http\Resources\DirectionResource;
use App\Shared\Application\Bus\QueryBus;
use Illuminate\Http\Request;

/**
 * Просмотр одного направления.
 */
final class ShowDirectionController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
    ) {}

    /**
     * GET /api/v1/directions/{direction}
     */
    public function __invoke(Request $request, int $direction): DirectionResource
    {

        /** @var DirectionResult $result */
        $result = $this->queries->ask(new ShowDirectionQuery((int) $request->user()->getKey(), $direction));

        return new DirectionResource($result);
    }
}
