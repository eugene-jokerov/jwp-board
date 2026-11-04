<?php

namespace Tests\Unit\Modules\User\Domain;

use App\Modules\User\Domain\Exceptions\EmailAlreadyRegistered;
use App\Modules\User\Domain\Services\UserRegistrar;
use App\Modules\User\Domain\ValueObjects\Email;
use App\Modules\User\Domain\ValueObjects\Password;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\User\Support\InMemoryUserRepository;

/**
 * Доменное правило: один email — один пользователь.
 */
final class UserRegistrarTest extends TestCase
{
    public function test_registers_new_user(): void
    {
        $repository = new InMemoryUserRepository;
        $registrar = new UserRegistrar($repository);

        $user = $registrar->register(
            Email::fromString('new@example.com'),
            Password::fromString('secret-password'),
        );

        self::assertSame('new@example.com', $user->email);
        self::assertNull($user->email_verified_at);
        self::assertCount(1, $repository->created);
    }

    public function test_rejects_already_registered_email_in_any_case(): void
    {
        $repository = new InMemoryUserRepository;
        $repository->seed('taken@example.com');
        $registrar = new UserRegistrar($repository);

        $this->expectException(EmailAlreadyRegistered::class);

        try {
            $registrar->register(
                Email::fromString('TAKEN@example.com'),
                Password::fromString('secret-password'),
            );
        } finally {
            self::assertSame([], $repository->created);
        }
    }
}
