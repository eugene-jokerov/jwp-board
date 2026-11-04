<?php

namespace App\Modules\User\Application\Results;

use App\Modules\User\Domain\User;

/**
 * Результат регистрации: созданный пользователь.
 *
 * Токен здесь не выдаётся: входное доказательство (Bearer-токен или веб-сессия)
 * зависит от канала клиента и выдаётся на уровне auth-адаптеров.
 */
final readonly class RegistrationResult
{
    public function __construct(
        public User $user,
    ) {}
}
