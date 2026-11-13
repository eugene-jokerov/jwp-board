<?php

namespace App\Modules\User\Application\Commands\IssueBearerToken;

use App\Modules\User\Domain\User;

/**
 * Команда выдачи Bearer-токена уже аутентифицированному пользователю.
 *
 * Нужна там, где учётные данные проверены другим сценарием (регистрация):
 * сценарий создания пользователя не знает про канал клиента, а токен —
 * доказательство подлинности, которое выдаёт этот сценарий.
 */
final readonly class IssueBearerTokenCommand
{
    public function __construct(
        public User $user,
        public string $deviceName,
    ) {}
}
