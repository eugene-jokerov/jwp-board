<?php

namespace App\Modules\Task\Infrastructure\Http\Requests;

use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\TaskDescription;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация изменения карточки.
 *
 * Все поля необязательны. Колонка меняется этим же запросом (поле column):
 * доменное правило перехода проверяет хендлер команды и отдаёт 409, если
 * смену блокируют дочерние карточки в работе.
 */
final class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:'.TaskTitle::MAX_LENGTH],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.TaskDescription::MAX_LENGTH],
            'column' => ['sometimes', Rule::enum(CardColumn::class)],
            'direction_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'initiative_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'project_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'название',
            'description' => 'описание',
            'column' => 'колонка',
            'direction_id' => 'направление',
            'initiative_id' => 'инициатива',
            'project_id' => 'проект',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.max' => 'Название не может быть длиннее :max символов.',
            'description.max' => 'Описание не может быть длиннее :max символов.',
            'column.enum' => 'Неизвестная колонка.',
        ];
    }

    /**
     * Переданные ссылки иерархии (только ключи, которые есть в запросе).
     *
     * @return array{direction_id?: int|null, initiative_id?: int|null, project_id?: int|null}
     */
    public function toLinkPayload(): array
    {
        $links = [];

        foreach (['direction_id', 'initiative_id', 'project_id'] as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                $links[$field] = $value === null ? null : (int) $value;
            }
        }

        return $links;
    }

    /**
     * Переданные поля карточки: заголовок, описание, колонка.
     *
     * @return array{title?: string, description?: string|null, column?: string}
     */
    public function toFieldPayload(): array
    {
        $fields = [];

        if ($this->has('title')) {
            $fields['title'] = $this->string('title')->toString();
        }

        if ($this->has('description')) {
            $value = $this->input('description');
            $fields['description'] = $value === null ? null : (string) $value;
        }

        if ($this->has('column')) {
            $fields['column'] = $this->string('column')->toString();
        }

        return $fields;
    }
}
