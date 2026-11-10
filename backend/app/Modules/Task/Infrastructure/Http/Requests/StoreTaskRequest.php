<?php

namespace App\Modules\Task\Infrastructure\Http\Requests;

use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardPriority;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Domain\ValueObjects\TaskDescription;
use App\Modules\Task\Domain\ValueObjects\TaskSlug;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;
use Closure;
use DateTimeImmutable;
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
            // Приоритет необязателен: без него карточка создаётся со средним —
            // то же значение, что подставляет колонка в базе.
            'priority' => ['sometimes', Rule::enum(CardPriority::class)],
            // Степень проработки: true — идея, false — готова к реализации.
            // Флаг необязателен: без него карточка создаётся готовой к работе.
            'idea' => ['sometimes', 'boolean'],
            'title' => ['required', 'string', 'max:'.TaskTitle::MAX_LENGTH],
            // Кодовое название необязательно и без ограничений по символам —
            // проверяется только длина. Правило `max` у строки считает символы
            // (mb_strlen), а не байты, поэтому кириллица укладывается в лимит
            // так же, как латиница: 16 символов проходят, 17 — нет.
            'slug' => ['sometimes', 'nullable', 'string', 'max:'.TaskSlug::MAX_LENGTH],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.TaskDescription::MAX_LENGTH],
            // Формат фиксирован: дата `Y-m-d`, время — необязательно
            // (`H:i` / `H:i:s`). Несколько правил date_format здесь не годятся:
            // они проверяются одновременно, а не как альтернативы. Существование
            // самой даты (30 февраля и т. п.) проверяет value object.
            'deadline' => ['sometimes', 'nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $this->deadlineRule()],
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
            'type' => 'тип',
            'column' => 'колонка',
            'priority' => 'приоритет',
            'idea' => 'степень проработки',
            'title' => 'название',
            'slug' => 'кодовое название',
            'description' => 'описание',
            'deadline' => 'дедлайн',
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
            'type.required' => 'Укажите тип карточки.',
            'type.enum' => 'Неизвестный тип карточки.',
            'column.required' => 'Укажите колонку.',
            'column.enum' => 'Неизвестная колонка.',
            'priority.enum' => 'Неизвестный приоритет карточки.',
            'idea.boolean' => 'Степень проработки — «идея» или «на реализацию».',
            'title.required' => 'Укажите название карточки.',
            'title.max' => 'Название не может быть длиннее :max символов.',
            'slug.max' => 'Кодовое название не может быть длиннее :max символов.',
            'description.max' => 'Описание не может быть длиннее :max символов.',
            'deadline.regex' => 'Неверная дата дедлайна.',
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
            // Приоритет передаётся только если пришёл в запросе: без поля
            // команда ставит средний, как и колонка в базе по умолчанию.
            ...($this->has('priority') ? ['priority' => $this->string('priority')->toString()] : []),
            // Степень проработки передаётся всегда явным boolean: без флага
            // карточка считается готовой к реализации. `boolean`-правило уже
            // проверило тип, поэтому `null` здесь означать нечего.
            'idea' => $this->boolean('idea'),
            'title' => $this->string('title')->toString(),
            // Кодовое название передаётся только если пришло в запросе: пустая
            // строка нормализуется в null уже в value object.
            ...($this->has('slug') ? ['slug' => $this->input('slug')] : []),
            'description' => $this->input('description'),
            // Дедлайн передаётся только если он пришёл в запросе: отсутствие
            // ключа — «дедлайна нет», явный null — тоже, а пустая строка
            // нормализуется в null уже в value object.
            ...($this->has('deadline') ? ['deadline' => $this->input('deadline')] : []),
            'direction_id' => $this->input('direction_id'),
            'initiative_id' => $this->input('initiative_id'),
            'project_id' => $this->input('project_id'),
        ];
    }
}
