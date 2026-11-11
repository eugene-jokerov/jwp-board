<?php

namespace App\Modules\User\Infrastructure\Http\Requests;

use App\Modules\User\Domain\ValueObjects\MetaKey;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация сохранения настроек пользователя.
 *
 * Тело — объект `settings` вида «ключ => значение»: клиент присылает только
 * изменившиеся настройки. Значение — произвольный JSON (объект свёрнутых
 * уровней/колонок), поэтому проверяется только его форма и размер, а не
 * структура: содержимое настройки знает клиент.
 */
final class UpdateUserSettingsRequest extends FormRequest
{
    /**
     * Предел размера значения настройки в байтах — совпадает с колонкой
     * `meta_value` (TEXT). Лимит в байтах, а не в символах: база считает байты.
     */
    public const int MAX_VALUE_BYTES = 65535;

    /**
     * Настройки меняет только аутентифицированный пользователь (auth:sanctum).
     */
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
            'settings' => [
                'required',
                'array',
                'min:1',
                // Имена ключей проверяются здесь же: правила `settings.*`
                // смотрят на значения, а ключ — доменное ограничение (MetaKey).
                function (string $attribute, mixed $value, Closure $fail): void {
                    foreach (array_keys((array) $value) as $key) {
                        try {
                            MetaKey::fromString((string) $key);
                        } catch (\InvalidArgumentException $e) {
                            $fail($e->getMessage());

                            return;
                        }
                    }
                },
            ],
            // `max` у строки считает символы, а колонка `meta_value` — байты
            // (TEXT = 65535 байт): кириллица в UTF-8 занимает два байта, и
            // строка в 65535 символов не сохранилась бы. Предел задан в байтах,
            // а не символах, поэтому правило проверяет strlen.
            'settings.*' => ['present', 'nullable', 'json', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === null) {
                    return;
                }

                if (strlen((string) $value) > self::MAX_VALUE_BYTES) {
                    $fail('Значение настройки не может быть длиннее '.self::MAX_VALUE_BYTES.' байт.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'settings' => 'настройки',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'settings.required' => 'Передайте хотя бы одну настройку.',
            'settings.array' => 'Настройки передаются объектом «ключ — значение».',
            'settings.min' => 'Передайте хотя бы одну настройку.',
            'settings.*.json' => 'Значение настройки должно быть корректным JSON.',
        ];
    }

    /**
     * Пары «ключ — значение» для команды.
     *
     * @return array<string, string|null>
     */
    public function toCommandPayload(): array
    {
        /** @var array<string, string|null> $settings */
        $settings = $this->input('settings', []);

        return $settings;
    }
}
