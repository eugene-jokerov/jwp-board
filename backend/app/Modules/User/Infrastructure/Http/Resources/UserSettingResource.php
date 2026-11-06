<?php

namespace App\Modules\User\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Представление одной настройки пользователя: ключ и значение.
 *
 * Значение хранится текстом, но клиенту оно нужно структурой (свёрнутые
 * уровни и колонки — объекты). Поэтому JSON декодируется при отдаче: клиент
 * получает готовый объект, не разбирая строки сам.
 *
 * @property-read string $resource ключ настройки
 * @property-read string|null $value сырое значение
 */
final class UserSettingResource extends JsonResource
{
    /**
     * Запись с ключом и значением.
     *
     * @param  string  $key  ключ настройки
     * @param  string|null  $value  сырое (обычно JSON) значение
     */
    public static function forSetting(string $key, ?string $value): self
    {
        $resource = new self($key);
        $resource->value = $value;

        return $resource;
    }

    /**
     * Сырое значение настройки.
     */
    public ?string $value = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource,
            'value' => $this->decodeValue($this->value),
        ];
    }

    /**
     * Декодировать JSON, иначе отдать строку как есть.
     *
     * Формат значения не валидируется: сервер хранит то, что прислал клиент,
     * и не должен падать на неожиданном содержимом — нечитаемый JSON
     * возвращается обычной строкой.
     */
    private function decodeValue(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
