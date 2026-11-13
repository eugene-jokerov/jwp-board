<?php

namespace App\Modules\Direction\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Direction\Application\Queries\ListDirections\ListDirectionsQuery;
use App\Modules\Direction\Application\Results\DirectionResult;
use App\Modules\Direction\Domain\Contracts\DirectionLimits;
use App\Modules\Direction\Infrastructure\Http\Resources\DirectionListResource;
use App\Modules\Direction\Infrastructure\Http\Resources\DirectionResource;
use App\Shared\Application\Bus\QueryBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Список направлений текущего пользователя.
 *
 * Контроллер — входной адаптер: собирает запрос прикладного слоя, отдаёт его
 * в шину и оборачивает результат в ресурс. Бизнес-логики здесь нет.
 *
 * Ответ — `{"data": [...], "meta": {"limit": 15}}`: рядом с направлениями едет
 * лимит из порта DirectionLimits (config('direction.max_per_user')), чтобы
 * клиент показывал счётчик «n из k» и не дублировал число у себя. Лимит лежит
 * в теле, а не в заголовке: веб-версия ходит в API с другого origin, а заголовок
 * пришлось бы отдельно экспонировать в CORS ради значения из тела.
 */
final class IndexDirectionController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly DirectionLimits $limits,
    ) {}

    /**
     * GET /api/v1/directions
     */
    public function __invoke(Request $request): JsonResponse
    {

        /** @var list<DirectionResult> $directions */
        $directions = $this->queries->ask(new ListDirectionsQuery((int) $request->user()->getKey()));

        return (new DirectionListResource(
            DirectionResource::collection($directions),
            $this->limits->maxPerUser(),
        ))->response();
    }
}
