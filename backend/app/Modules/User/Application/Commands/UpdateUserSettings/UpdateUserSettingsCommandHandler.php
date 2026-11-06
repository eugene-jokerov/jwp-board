<?php

namespace App\Modules\User\Application\Commands\UpdateUserSettings;

use App\Modules\User\Domain\Contracts\UserMetaRepository;
use App\Modules\User\Domain\ValueObjects\MetaKey;

/**
 * Хендлер команды сохранения настроек.
 *
 * Шина команд оборачивает хендлер в транзакцию: пачка настроек сохраняется
 * целиком или не сохраняется вовсе. В ответ возвращается полное состояние
 * пользователя — клиент сразу видит, что реально лежит на сервере.
 */
final readonly class UpdateUserSettingsCommandHandler
{
    public function __construct(
        private UserMetaRepository $meta,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function handle(UpdateUserSettingsCommand $command): array
    {
        foreach ($command->settings as $key => $value) {
            $this->meta->put($command->userId, MetaKey::fromString($key), $value);
        }

        return $this->meta->allForUser($command->userId);
    }
}
