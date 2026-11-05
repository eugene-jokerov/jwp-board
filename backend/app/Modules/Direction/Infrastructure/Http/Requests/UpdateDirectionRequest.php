<?php

namespace App\Modules\Direction\Infrastructure\Http\Requests;

use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация изменения направления.
 *
 * Оба поля необязательны: PATCH может менять только название, только цвет
 * или ничего (тогда команда вернёт направление без изменений).
 */
final class UpdateDirectionRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:'.DirectionTitle::MAX_LENGTH],
            'color' => ['sometimes', Rule::enum(DirectionColor::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'название',
            'color' => 'цвет',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.max' => 'Название не может быть длиннее :max символов.',
            'color.enum' => 'Выберите цвет из палитры направлений.',
        ];
    }

    /**
     * Переданные поля; отсутствующее — null («не менять»).
     *
     * @return array{title: string|null, color: string|null}
     */
    public function toCommandPayload(): array
    {
        return [
            'title' => $this->has('title') ? $this->string('title')->toString() : null,
            'color' => $this->has('color') ? $this->string('color')->toString() : null,
        ];
    }
}
