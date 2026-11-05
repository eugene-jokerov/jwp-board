<?php

namespace App\Modules\Direction\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Direction\Application\Queries\ListDirections\ListDirectionsQuery;
use App\Modules\Direction\Application\Results\DirectionResult;
use App\Modules\Direction\Infrastructure\Http\Resources\DirectionResource;
use App\Modules\User\Domain\User;
use App\Shared\Application\Bus\QueryBus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Список направлений текущего пользователя.
 *
 * Контроллер — входной адаптер: собирает запрос прикладного слоя, отдаёт его
 * в шину и оборачивает результат в ресурс. Бизнес-логики здесь нет.
 */
final class IndexDirectionController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
    ) {}

    /**
     * GET /api/v1/directions
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        /** @var list<DirectionResult> $directions */
        $directions = $this->queries->ask(new ListDirectionsQuery((int) $user->getKey()));

        return DirectionResource::collection($directions);
    }
}
