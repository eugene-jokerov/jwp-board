<?php

namespace App\Modules\User\Domain\Contracts;

use App\Modules\User\Domain\User;

/**
 * Порт выдачи токена доступа.
 *
 * Сценарий входа и регистрации не знает, как именно устроен токен
 * (персональный токен Sanctum, JWT, сессия): это решает адаптер канала.
 */
interface TokenIssuer
{
    /**
     * Выдать токен пользователю и вернуть его текст.
     *
     * @param  string  $name  метка токена (обычно имя устройства клиента)
     */
    public function issue(User $user, string $name): string;
}
