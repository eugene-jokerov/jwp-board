<?php

namespace App\Modules\User\Application\Queries\ListUserSettings;

/**
 * Запрос настроек текущего пользователя.
 *
 * Содержит только идентификатор: какие именно настройки нужны, решает клиент —
 * он забирает все пары и берёт свои ключи. Отдельного запроса «настройка X»
 * нет: настроек мало, а один запрос на весь экран дешевле нескольких.
 */
final readonly class ListUserSettingsQuery
{
    public function __construct(
        public int $userId,
    ) {}
}
