<?php

namespace App\Modules\Task\Infrastructure\Http\Requests;

use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация фильтров списка карточек.
 *
 * Фильтры читаются из query-строки: опечатка в имени или значении фильтра
 * должна давать 422, а не «весь список без фильтра» — иначе клиент молча
 * покажет лишние карточки и баг останется незамеченным.
 *
 * Запрос без `column`, `limit` и `offset` считается первичной загрузкой доски
 * (см. isInitialLoad): закрытые колонки в нём отдаются порцией
 * на каждую пару «уровень + колонка», а не целиком.
 */
final class IndexTaskRequest extends FormRequest
{
    /**
     * Максимальный размер страницы.
     *
     * Ограничение защищает сервер от запроса «весь список одним куском»:
     * догрузка закрытых колонок идёт страницами, и размер запрашивает клиент.
     */
    public const int MAX_LIMIT = 500;

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
            // Страница выборки: `offset` без `limit` отклоняет withValidator —
            // бессмысленная пара почти всегда опечатка клиента, и молча
            // проигнорированное смещение скрыло бы баг пагинации.
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'offset' => ['sometimes', 'integer', 'min:0'],
            'order' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * Смещение имеет смысл только вместе с размером страницы, а страница —
     * только по конкретной колонке.
     *
     * Лимит закрытых карточек действует на пару «уровень + колонка», поэтому
     * догрузка всегда адресная. Страница без `column` вернула бы кусок
     * смешанного списка и сломала бы счётчики пары, а бессмысленная пара
     * почти всегда — опечатка клиента, и молча проигнорировать её нельзя.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasLimit = $this->query('limit') !== null;

            if ($this->query('offset') !== null && ! $hasLimit) {
                $validator->errors()->add('offset', 'Смещение передаётся только вместе с размером страницы.');
            }

            if ($hasLimit && $this->query('column') === null) {
                $validator->errors()->add('limit', 'Размер страницы передаётся только вместе с колонкой.');
            }
        });
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
            'limit' => 'размер страницы',
            'offset' => 'смещение',
            'order' => 'порядок',
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
            'limit.integer' => 'Размер страницы должен быть числом.',
            'limit.min' => 'Размер страницы должен быть не меньше 1.',
            'limit.max' => 'Размер страницы не может превышать '.self::MAX_LIMIT.'.',
            'offset.integer' => 'Смещение должно быть числом.',
            'offset.min' => 'Смещение не может быть отрицательным.',
            'order.in' => 'Порядок сортировки — asc или desc.',
        ];
    }

    /**
     * Размер страницы; не задан — страницы нет, отдаём весь список.
     */
    public function limitValue(): ?int
    {
        return $this->intValue('limit');
    }

    /**
     * Первичная загрузка доски: клиент запросил список без колонки и страницы.
     *
     * В этом режиме сервер сам решает, сколько отдать из закрытых колонок
     * (CardColumn::CLOSED_CARDS_PER_LEVEL на пару «уровень + колонка»),
     * а остальное клиент догружает страницами.
     */
    public function isInitialLoad(): bool
    {
        return $this->query('column') === null
            && $this->query('limit') === null
            && $this->query('offset') === null;
    }

    /**
     * Смещение страницы; не задано — с начала выборки.
     */
    public function offsetValue(): int
    {
        return $this->intValue('offset') ?? 0;
    }

    /**
     * Направление сортировки по идентификатору; по умолчанию — от старых к новым.
     */
    public function orderValue(): string
    {
        return $this->query('order') === 'desc' ? 'desc' : 'asc';
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
