<?php

namespace App\Modules\Task\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Task\Application\Queries\ListTasks\ListTasksQuery;
use App\Modules\Task\Application\Results\ListTasksResult;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Infrastructure\Http\Requests\IndexTaskRequest;
use App\Modules\Task\Infrastructure\Http\Resources\TaskSummaryResource;
use App\Modules\User\Domain\User;
use App\Shared\Application\Bus\QueryBus;
use Illuminate\Http\JsonResponse;

/**
 * Список карточек текущего пользователя с фильтрами доски.
 *
 * Фильтры валидируются IndexTaskRequest: неизвестный тип, колонка или
 * нечисловой идентификатор дают 422, а не тихо отключённый фильтр.
 *
 * Два режима, оба отдают `{"data": [...], "meta": {...}}`:
 *  - первичная загрузка (без `column`/`limit`/`offset`): Беклог и «В работе»
 *    приходят целиком, в «Готово» и «Отмене» — не больше
 *    CardColumn::CLOSED_CARDS_PER_LEVEL карточек на каждую пару «уровень +
 *    колонка»;
 *  - страница (`limit`/`offset` вместе с `column`): ровно запрошенный кусок,
 *    так клиент догружает следующие карточки закрытой колонки своего уровня.
 *
 * Счётчики лежат в `meta` рядом с `data`, а не в заголовках: веб-версия ходит
 * в API с другого origin, и заголовки пришлось бы отдельно экспонировать
 * в CORS ради тех же чисел, которые проще прочитать из тела.
 * `meta.columns` — сколько карточек под фильтром в каждой колонке (для бейджа
 * колонки), `meta.closed_totals` — полное число закрытых карточек по паре
 * «уровень + колонка»: по нему клиент рисует «показать ещё», когда выборка
 * урезана.
 *
 * Карточки отдаются в кратком виде (TaskSummaryResource): описания в списке
 * нет, полный набор полей — у детального просмотра `GET /tasks/{task}`.
 */
final class IndexTaskController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
    ) {}

    /**
     * GET /api/v1/tasks?type=&column=&direction_id=&initiative_id=&project_id=&limit=&offset=&order=
     */
    public function __invoke(IndexTaskRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var CardType|null $type */
        $type = $request->enumValue('type', CardType::class);

        /** @var CardColumn|null $column */
        $column = $request->enumValue('column', CardColumn::class);

        /** @var ListTasksResult $result */
        $result = $this->queries->ask(new ListTasksQuery(
            (int) $user->getKey(),
            $type,
            $column,
            $request->intValue('direction_id'),
            $request->intValue('initiative_id'),
            $request->intValue('project_id'),
            $request->limitValue(),
            $request->offsetValue(),
            $request->orderValue(),
            $request->isInitialLoad(),
        ));

        // Ответ собираем явно: рядом с `data` нужен `meta`, а `with()` в Laravel
        // кладёт поля плоско — на одном уровне с ключом `data`, а не в `meta`.
        // Данные берём из ресурсов напрямую: сериализация в JSON и обратный
        // разбор строки дали бы ту же структуру, но через лишний проход.
        $payload = [
            'data' => TaskSummaryResource::collection($result->items)->resolve($request),
            'meta' => [
                'total' => $result->total,
                // Счётчики по всем парам: отсутствующая в выборке пара — это
                // ноль, а не пропущенный ключ, иначе клиенту пришлось бы
                // подставлять нули самому.
                'columns' => $this->columnTotals($result),
                'closed_totals' => $this->closedTotals($result),
            ],
        ];

        return new JsonResponse($payload);
    }

    /**
     * Полное число карточек каждой колонки под фильтром.
     *
     * Считается по тем же фильтрам, что и выборка: при `column=complete`
     * карточки распределены только по этой колонке, остальные — нули.
     * Число включает карточки всех уровней, в том числе открытых колонок.
     *
     * @return array<string, int>
     */
    private function columnTotals(ListTasksResult $result): array
    {
        $totals = [];

        foreach (CardColumn::ordered() as $column) {
            $totals[$column->value] = $result->totalByColumn[$column->value] ?? 0;
        }

        return $totals;
    }

    /**
     * Полное число закрытых карточек по парам «уровень + колонка».
     *
     * Форма фиксированная: ключ — закрытая колонка, значение — объект
     * «тип карточки => всего карточек» со всеми типами. Так фронту не нужно
     * проверять наличие ключа, а «есть ещё» — это сравнение числа с уже
     * загруженным количеством карточек пары.
     *
     * @return array<string, array<string, int>>
     */
    private function closedTotals(ListTasksResult $result): array
    {
        $totals = [];

        foreach (CardColumn::ordered() as $column) {
            if (! $column->isClosed()) {
                continue;
            }

            $byLevel = [];

            foreach (CardType::cases() as $type) {
                $byLevel[$type->value] = $result->closedByLevel[$column->value][$type->value] ?? 0;
            }

            $totals[$column->value] = $byLevel;
        }

        return $totals;
    }
}
