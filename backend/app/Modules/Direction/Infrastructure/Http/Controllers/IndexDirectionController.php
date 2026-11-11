<?php

namespace App\Modules\Direction\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Direction\Application\Queries\ListDirections\ListDirectionsQuery;
use App\Modules\Direction\Application\Results\DirectionResult;
use App\Modules\Direction\Infrastructure\Http\Resources\DirectionResource;
use App\Modules\User\Domain\User;
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
 * лимит из config('direction.max_per_user'), чтобы клиент показывал счётчик
 * «n из k» и не дублировал число у себя. Лимит лежит в теле, а не в заголовке:
 * веб-версия ходит в API с другого origin, и заголовок пришлось бы отдельно
 * экспонировать в CORS ради значения, которое проще прочитать из тела.
 */
final class IndexDirectionController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
    ) {}

    /**
     * GET /api/v1/directions
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var list<DirectionResult> $directions */
        $directions = $this->queries->ask(new ListDirectionsQuery((int) $user->getKey()));

        // Ответ собираем явно: рядом с `data` нужен `meta`, а `with()` в Laravel
        // кладёт поля плоско — на одном уровне с ключом `data`, а не в `meta`.
        // Данные берём из ресурсов напрямую: сериализация в JSON и обратный
        // разбор строки дали бы ту же структуру, но через лишний проход.
        $payload = [
            'data' => DirectionResource::collection($directions)->resolve($request),
            'meta' => [
                'limit' => (int) config('direction.max_per_user'),
            ],
        ];

        return new JsonResponse($payload);
    }
}
