<?php

namespace App\Modules\User\Infrastructure\Persistence;

use App\Modules\User\Domain\Contracts\UserRepository;
use App\Modules\User\Domain\User;
use App\Modules\User\Domain\ValueObjects\Email;
use App\Modules\User\Domain\ValueObjects\Password;

/**
 * Eloquent-реализация порта UserRepository.
 */
final class EloquentUserRepository implements UserRepository
{
    public function findByEmail(Email $email): ?User
    {
        return User::query()->where('email', $email->value())->first();
    }

    public function emailExists(Email $email): bool
    {
        return User::query()->where('email', $email->value())->exists();
    }

    public function create(Email $email, Password $password, bool $verified = false): User
    {
        // Пароль хешируется кастом `hashed` модели User.
        return User::query()->create([
            'email' => $email->value(),
            'password' => $password->value(),
            'email_verified_at' => $verified ? now() : null,
        ]);
    }
}
