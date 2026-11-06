<?php

namespace App\Modules\User\Application\Commands\DeleteUserSettings;

use App\Modules\User\Domain\ValueObjects\MetaKey;

/**
 * Команда удаления настройки пользователя.
 *
 * Нужна для сброса состояния: удалённая настройка означает «значение по
 * умолчанию», а не «пустое значение».
 */
final readonly class DeleteUserSettingsCommand
{
    public function __construct(
        public int $userId,
        public MetaKey $key,
    ) {}
}
