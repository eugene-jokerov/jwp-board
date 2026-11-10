<?php

namespace App\Modules\Task\Infrastructure\Http\Resources;

use App\Modules\Task\Application\Results\TaskResult;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Полное представление карточки в ответах API.
 *
 * Используется там, где нужны все поля: `GET /tasks/{task}` (детальный
 * просмотр), а также ответы создания и изменения карточки. В списке доски
 * (`GET /tasks`) используется TaskSummaryResource — без описания, чтобы не
 * тащить в выборку длинные тексты.
 *
 * direction_id — направление карточки на любом уровне: у инициативы это её
 * собственное направление, у проекта и задачи — унаследованное от родителя.
 * Клиент строит по нему свимлайны, не пытаясь угадать направление по цвету.
 * color — ключ палитры этого направления (c1…c19) или null, если направления
 * нет: клиент красит такую карточку нейтральным цветом.
 * allowed_transitions — колонки, в которые карточку можно перевести сейчас;
 * их считает доменная политика, клиент только отображает.
 * deadline — необязательный срок карточки в ISO-8601; null — срока нет.
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
            'deadline' => $this->resource->deadline,
            'direction_id' => $this->resource->directionId,
            'initiative_id' => $this->resource->initiativeId,
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
