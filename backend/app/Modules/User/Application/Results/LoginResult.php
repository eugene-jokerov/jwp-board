<?php

namespace App\Modules\User\Application\Results;

use App\Modules\User\Domain\User;

/**
 * Результат входа с Bearer-токеном.
 *
 * Токен существует только в момент выдачи (Sanctum хранит его хеш),
 * поэтому приходит в результате сценария и сразу уходит в ответ API.
 */
final readonly class LoginResult
{
    public function __construct(
        public User $user,
        public string $plainTextToken,
    ) {}
}
