<?php

namespace App\Modules\Direction\Infrastructure\Http\Requests;

use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация создания направления.
 *
 * Авторизация не нужна: маршрут уже закрыт `auth:sanctum`, а user_id берётся
 * из аутентификации.
 */
final class StoreDirectionRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:'.DirectionTitle::MAX_LENGTH],
            'color' => ['required', Rule::enum(DirectionColor::class)],
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
            'title.required' => 'Укажите название направления.',
            'title.max' => 'Название не может быть длиннее :max символов.',
            'color.required' => 'Выберите цвет направления.',
            'color.enum' => 'Выберите цвет из палитры направлений.',
        ];
    }

    /**
     * @return array{title: string, color: string}
     */
    public function toCommandPayload(): array
    {
        return [
            'title' => $this->string('title')->toString(),
            'color' => $this->string('color')->toString(),
        ];
    }
}
