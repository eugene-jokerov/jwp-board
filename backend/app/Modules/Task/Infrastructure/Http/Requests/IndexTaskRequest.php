<?php

namespace App\Modules\Task\Infrastructure\Http\Requests;

use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация фильтров списка карточек.
 *
 * Фильтры читаются из query-строки: опечатка в имени или значении фильтра
 * должна давать 422, а не «весь список без фильтра» — иначе клиент молча
 * покажет лишние карточки и баг останется незамеченным.
 */
final class IndexTaskRequest extends FormRequest
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
            'type' => ['sometimes', Rule::enum(CardType::class)],
            'column' => ['sometimes', Rule::enum(CardColumn::class)],
            'direction_id' => ['sometimes', 'integer', 'min:1'],
            'initiative_id' => ['sometimes', 'integer', 'min:1'],
            'project_id' => ['sometimes', 'integer', 'min:1'],
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
            'type.enum' => 'Неизвестный тип карточки.',
            'column.enum' => 'Неизвестная колонка.',
            'direction_id.integer' => 'Идентификатор направления должен быть числом.',
            'initiative_id.integer' => 'Идентификатор инициативы должен быть числом.',
            'project_id.integer' => 'Идентификатор проекта должен быть числом.',
        ];
    }

    /**
     * Значение фильтра как перечисление; отсутствующий фильтр — null.
     *
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    public function enumValue(string $field, string $enum): mixed
    {
        $value = $this->query($field);

        return is_string($value) ? $enum::tryFrom($value) : null;
    }

    /**
     * Числовой фильтр; отсутствующий фильтр — null.
     */
    public function intValue(string $field): ?int
    {
        $value = $this->query($field);

        return is_numeric($value) ? (int) $value : null;
    }
}
