<?php

namespace App\Modules\Direction\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ответ списка направлений: `data` с ресурсами и `meta.limit`.
 *
 * Обёртка `JsonResource` нужна ради CORS: метаданные (`additional`) попадают
 * в заголовки только при преобразовании в полный HTTP-ответ. В теле `meta`
 * стоит на верхнем уровне рядом с `data` — клиент читает счётчик «n из k»
 * без отдельного запроса и без заголовка, который пришлось бы экспонировать.
 */
final class DirectionListResource extends JsonResource
{
    /**
     * Лимит направлений на пользователя.
     */
    private int $limit = 0;

    /**
     * @param  AnonymousResourceCollection<JsonResource>  $resource  коллекция DirectionResource
     */
    public function __construct(AnonymousResourceCollection $resource, int $limit)
    {
        parent::__construct($resource);

        $this->limit = $limit;
    }

    /**
     * @return array{data: mixed, meta: array{limit: int}}
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->resource->resolve($request),
            'meta' => ['limit' => $this->limit],
        ];
    }
}
