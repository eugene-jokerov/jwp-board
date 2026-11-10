<?php

namespace App\Modules\Task\Infrastructure\Http\Resources;

use App\Modules\Task\Application\Results\TaskResult;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Публичное представление карточки в списке доски.
 *
 * Полей ровно столько, сколько нужно для отрисовки доски: описание сюда не
 * попадает — оно может занимать до TaskDescription::MAX_LENGTH символов, а
 * список отдаёт сотни карточек сразу. Полное представление (с описанием) —
 * TaskResource, его отдают `GET /tasks/{task}` и ответы создания/изменения.
 *
 * direction_id — направление карточки на любом уровне: у инициативы это её
 * собственное направление, у проекта и задачи — унаследованное от родителя.
 * Клиент строит по нему свимлайны, не пытаясь угадать направление по цвету.
 * color — ключ палитры этого направления (c1…c19) или null, если направления
 * нет: клиент красит такую карточку нейтральным цветом.
 * allowed_transitions — колонки, в которые карточку можно перевести сейчас;
 * их считает доменная политика, клиент только отображает.
 * deadline — срок карточки в ISO-8601 или null: доска рисует дату в плитке,
 * поэтому поле есть и в списке (оно короткое, в отличие от описания).
 * slug — кодовое название или null; поле короткое (≤16 символов), поэтому есть
 * и в списке: форма редактирования сверяет с ним начальное значение.
 * idea — степень проработки: true — ещё идея, false — готова к реализации.
 * Смысл имеет в беклоге (там доска ставит идеи ниже и рисует бейдж), но
 * отдаётся всегда: значение флага при смене колонки не сбрасывается.
 *
 * @property-read TaskResult $resource
 */
final class TaskSummaryResource extends JsonResource
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
            'idea' => $this->resource->idea,
            'title' => $this->resource->title,
            'slug' => $this->resource->slug,
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
