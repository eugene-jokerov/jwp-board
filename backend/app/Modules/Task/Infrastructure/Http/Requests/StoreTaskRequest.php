<?php

namespace App\Modules\Task\Infrastructure\Http\Requests;

use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Domain\ValueObjects\TaskDescription;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация создания карточки.
 *
 * Здесь проверяется только форма данных: тип и колонка — из перечислений,
 * ссылки иерархии — существующие id. Совместимость ссылок с типом карточки
 * (например, у инициативы обязателен direction_id) — доменное правило,
 * его проверяет TaskPlacement и отдаёт 422.
 */
final class StoreTaskRequest extends FormRequest
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
            'type' => ['required', Rule::enum(CardType::class)],
            'column' => ['required', Rule::enum(CardColumn::class)],
            'title' => ['required', 'string', 'max:'.TaskTitle::MAX_LENGTH],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.TaskDescription::MAX_LENGTH],
            'direction_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'iniciative_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'project_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'тип',
            'column' => 'колонка',
            'title' => 'название',
            'description' => 'описание',
            'direction_id' => 'направление',
            'iniciative_id' => 'инициатива',
            'project_id' => 'проект',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Укажите тип карточки.',
            'type.enum' => 'Неизвестный тип карточки.',
            'column.required' => 'Укажите колонку.',
            'column.enum' => 'Неизвестная колонка.',
            'title.required' => 'Укажите название карточки.',
            'title.max' => 'Название не может быть длиннее :max символов.',
            'description.max' => 'Описание не может быть длиннее :max символов.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toCommandPayload(): array
    {
        return [
            'type' => $this->string('type')->toString(),
            'column' => $this->string('column')->toString(),
            'title' => $this->string('title')->toString(),
            'description' => $this->input('description'),
            'direction_id' => $this->input('direction_id'),
            'iniciative_id' => $this->input('iniciative_id'),
            'project_id' => $this->input('project_id'),
        ];
    }
}
