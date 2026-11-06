<?php

namespace App\Modules\User\Application\Queries\ListUserSettings;

use App\Modules\User\Domain\Contracts\UserMetaRepository;

/**
 * Хендлер запроса настроек: вернуть все сохранённые пары «ключ — значение».
 *
 * Значения отдаются сырыми: прикладной слой не знает, что клиент хранит в
 * конкретной настройке, поэтому не разбирает и не валидирует содержимое.
 */
final readonly class ListUserSettingsQueryHandler
{
    public function __construct(
        private UserMetaRepository $meta,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function handle(ListUserSettingsQuery $query): array
    {
        return $this->meta->allForUser($query->userId);
    }
}
