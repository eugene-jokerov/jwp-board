<?php

namespace App\Modules\User\Application\Commands\DeleteUserSettings;

use App\Modules\User\Domain\Contracts\UserMetaRepository;

/**
 * Хендлер команды удаления настройки.
 *
 * Удаление идемпотентно: если настройки нет, сценарий завершается успешно —
 * результат для клиента один и тот же («настройка не сохранена»).
 */
final readonly class DeleteUserSettingsCommandHandler
{
    public function __construct(
        private UserMetaRepository $meta,
    ) {}

    public function handle(DeleteUserSettingsCommand $command): void
    {
        $meta = $this->meta->findForUser($command->userId, $command->key);

        if ($meta !== null) {
            $this->meta->delete($meta);
        }
    }
}
