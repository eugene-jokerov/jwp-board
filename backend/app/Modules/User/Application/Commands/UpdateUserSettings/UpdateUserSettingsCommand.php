<?php

namespace App\Modules\User\Application\Commands\UpdateUserSettings;

use App\Modules\User\Domain\ValueObjects\MetaKey;
use InvalidArgumentException;

/**
 * Команда сохранения настроек пользователя.
 *
 * Принимает пачку пар «ключ — значение»: клиент шлёт только изменившиеся
 * настройки, а каждая из них сохраняется UPSERT-ом. Значение — готовая строка
 * (обычно JSON), потому что формат содержимого знает клиент, а не сервер.
 */
final readonly class UpdateUserSettingsCommand
{
    /**
     * @param  array<string, string|null>  $settings  ключ => сырое значение
     */
    public function __construct(
        public int $userId,
        public array $settings,
    ) {}

    /**
     * Собрать команду, проверив форму ключей.
     *
     * @param  array<string, string|null>  $payload
     *
     * @throws InvalidArgumentException если ключ не проходит MetaKey
     */
    public static function fromArray(int $userId, array $payload): self
    {
        $settings = [];

        foreach ($payload as $key => $value) {
            // Ключ нормализуется value object'ом: в хранилище попадает только
            // каноничная форма, а не то, что прислал клиент.
            $settings[MetaKey::fromString((string) $key)->value()] = $value;
        }

        return new self($userId, $settings);
    }
}
