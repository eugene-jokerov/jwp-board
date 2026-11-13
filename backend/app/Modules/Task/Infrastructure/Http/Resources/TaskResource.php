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
 * slug — необязательное кодовое название (≤16 символов); null — метки нет.
 * idea — степень проработки: true — ещё идея, false — готова к реализации.
 * Смысл имеет в беклоге, но отдаётся всегда.
 * priority — приоритет карточки (low / medium / high). От колонки и уровня
 * не зависит и поэтому отдаётся всегда; в закрытых колонках клиент его
 * не показывает.
 *
 * Поля читаются из Application\Results\TaskResult — прикладного значения
 * карточки: адаптер ресурса не тянет доменную модель и её атрибуты.
 *
 * @mixin TaskResult
 */
final class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'column' => $this->column->value,
            'priority' => $this->priority->value,
            'idea' => $this->idea,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'deadline' => $this->deadline,
            'direction_id' => $this->directionId,
            'initiative_id' => $this->initiativeId,
            'project_id' => $this->projectId,
            'color' => $this->directionColor?->key(),
            'allowed_transitions' => array_map(
                static fn (CardColumn $column): string => $column->value,
                $this->allowedTransitions,
            ),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
