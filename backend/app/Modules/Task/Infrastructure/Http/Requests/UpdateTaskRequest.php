<?php

namespace App\Modules\Task\Infrastructure\Http\Requests;

use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardPriority;
use App\Modules\Task\Domain\ValueObjects\TaskDescription;
use App\Modules\Task\Domain\ValueObjects\TaskSlug;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;
use Closure;
use DateTimeImmutable;
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
            // Кодовое название необязательно и без ограничений по символам:
            // явный null очищает метку, отсутствие ключа её не трогает.
            // `max` у строки считает символы (mb_strlen), а не байты: 16
            // кириллических символов проходят так же, как 16 латинских.
            'slug' => ['sometimes', 'nullable', 'string', 'max:'.TaskSlug::MAX_LENGTH],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.TaskDescription::MAX_LENGTH],
            // Формат фиксирован: дата `Y-m-d`, время — необязательно
            // (`H:i` / `H:i:s`). Несколько правил date_format здесь не годятся:
            // они проверяются одновременно, а не как альтернативы. Существование
            // самой даты (30 февраля и т. п.) проверяет value object.
            'deadline' => ['sometimes', 'nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $this->deadlineRule()],
            'column' => ['sometimes', Rule::enum(CardColumn::class)],
            // Приоритет меняется независимо от колонки и уровня: ограничений
            // «только беклог», как у степени проработки, у него нет.
            'priority' => ['sometimes', Rule::enum(CardPriority::class)],
            // Степень проработки: true — идея, false — готова к реализации.
            // Отсутствие ключа — «не менять»: при смене колонки флаг сохраняется.
            'idea' => ['sometimes', 'boolean'],
            'direction_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'initiative_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'project_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Правило существования даты: 30 февраля и прочие несуществующие дни отклоняются.
     *
     * Регулярка проверяет только форму строки, а `createFromFormat` прощает
     * переполнение (30 февраля → 2 марта). Ошибки разбора видны в
     * getLastErrors(), поэтому дата проверяется ими, а не сравнением строки.
     */
    private function deadlineRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || trim($value) === '') {
                return;
            }

            $normalized = preg_replace('/[T ]/', ' ', trim($value)) ?? '';

            if (mb_strlen($normalized) === 10) {
                $normalized .= ' 00:00:00';
            } elseif (mb_strlen($normalized) === 16) {
                $normalized .= ':00';
            }

            $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $normalized);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                $fail('Неверная дата дедлайна.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'название',
            'slug' => 'кодовое название',
            'description' => 'описание',
            'deadline' => 'дедлайн',
            'column' => 'колонка',
            'priority' => 'приоритет',
            'idea' => 'степень проработки',
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
            'slug.max' => 'Кодовое название не может быть длиннее :max символов.',
            'description.max' => 'Описание не может быть длиннее :max символов.',
            'deadline.regex' => 'Неверная дата дедлайна.',
            'column.enum' => 'Неизвестная колонка.',
            'priority.enum' => 'Неизвестный приоритет карточки.',
            'idea.boolean' => 'Степень проработки — «идея» или «на реализацию».',
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
     * Переданные поля карточки: заголовок, кодовое название, описание, дедлайн,
     * колонка, приоритет, степень проработки.
     *
     * Ключи попадают в результат только если они пришли в запросе: отсутствие
     * ключа — «не менять», явный null — «очистить».
     *
     * @return array{title?: string, slug?: string|null, description?: string|null, deadline?: string|null, column?: string, priority?: string, idea?: bool}
     */
    public function toFieldPayload(): array
    {
        $fields = [];

        if ($this->has('title')) {
            $fields['title'] = $this->string('title')->toString();
        }

        if ($this->has('slug')) {
            $value = $this->input('slug');
            $fields['slug'] = $value === null ? null : (string) $value;
        }

        if ($this->has('description')) {
            $value = $this->input('description');
            $fields['description'] = $value === null ? null : (string) $value;
        }

        if ($this->has('deadline')) {
            $value = $this->input('deadline');
            $fields['deadline'] = $value === null ? null : (string) $value;
        }

        if ($this->has('column')) {
            $fields['column'] = $this->string('column')->toString();
        }

        if ($this->has('priority')) {
            $fields['priority'] = $this->string('priority')->toString();
        }

        if ($this->has('idea')) {
            $fields['idea'] = $this->boolean('idea');
        }

        return $fields;
    }
}
