<?php

namespace App\Modules\Direction\Infrastructure\Http\Resources;

use App\Modules\Direction\Application\Results\DirectionResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Публичное представление направления в ответах API.
 *
 * Клиент получает ключ палитры (`c1`…`c19`) и сам решает, каким оттенком его
 * показать: HEX — слой отображения, в API он не нужен.
 *
 * @property-read DirectionResult $resource
 */
final class DirectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'color' => $this->resource->color->key(),
        ];
    }
}
