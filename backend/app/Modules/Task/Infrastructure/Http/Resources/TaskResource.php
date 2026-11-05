<?php

namespace App\Modules\Task\Infrastructure\Http\Resources;

use App\Modules\Task\Application\Results\TaskResult;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Публичное представление карточки в ответах API.
 *
 * color — ключ палитры направления карточки (c1…c19) или null, если
 * направления нет: клиент красит такую карточку нейтральным цветом.
 * allowed_transitions — колонки, в которые карточку можно перевести сейчас;
 * их считает доменная политика, клиент только отображает.
 *
 * @property-read TaskResult $resource
 */
final class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type->value,
            'column' => $this->resource->column->value,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'direction_id' => $this->resource->directionId,
            'iniciative_id' => $this->resource->iniciativeId,
            'project_id' => $this->resource->projectId,
            'color' => $this->resource->directionColor?->key(),
            'allowed_transitions' => array_map(
                static fn (CardColumn $column): string => $column->value,
                $this->resource->allowedTransitions,
            ),
            'created_at' => $this->resource->createdAt,
            'updated_at' => $this->resource->updatedAt,
        ];
    }
}
