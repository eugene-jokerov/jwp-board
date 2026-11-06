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
            'settings.*' => ['present', 'nullable', 'json', 'max:65535'],
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
            'settings.*.max' => 'Значение настройки не может быть длиннее 65535 символов.',
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
