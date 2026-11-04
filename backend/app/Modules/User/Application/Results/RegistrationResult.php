<?php

namespace App\Modules\User\Application\Results;

use App\Modules\User\Domain\User;

/**
 * Результат регистрации: созданный пользователь и его plain-text токен.
 *
 * Plain-text токен существует только в этом объекте — в модели хранится хеш.
 */
final readonly class RegistrationResult
{
    public function __construct(
        public User $user,
        public string $plainTextToken,
    ) {}
}
