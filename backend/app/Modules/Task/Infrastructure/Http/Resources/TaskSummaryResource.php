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
 * priority — приоритет карточки (low / medium / high): доска выводит его
 * бейджем в плитке. Значение короткое и нужно для отрисовки списка, поэтому
 * есть и в кратком представлении.
 *
 * Поля читаются из Application\Results\TaskResult — прикладного значения
 * карточки: адаптер ресурса не тянет доменную модель и её атрибуты.
 *
 * @mixin TaskResult
 */
final class TaskSummaryResource extends JsonResource
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
