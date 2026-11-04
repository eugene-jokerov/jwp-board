<?php

namespace Tests\Unit\Modules\User\Domain;

use App\Modules\User\Domain\Contracts\UserRepository;
use App\Modules\User\Domain\Exceptions\EmailAlreadyRegistered;
use App\Modules\User\Domain\Services\UserRegistrar;
use App\Modules\User\Domain\User;
use App\Modules\User\Domain\ValueObjects\Email;
use App\Modules\User\Domain\ValueObjects\Password;
use PHPUnit\Framework\TestCase;

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

/**
 * Тестовый двойник порта UserRepository: хранит созданных пользователей в памяти.
 */
final class InMemoryUserRepository implements UserRepository
{
    /**
     * @var list<string>
     */
    public array $created = [];

    /**
     * @var list<string>
     */
    private array $existing = [];

    public function seed(string $email): void
    {
        $this->existing[] = $email;
    }

    public function findByEmail(Email $email): ?User
    {
        return null;
    }

    public function emailExists(Email $email): bool
    {
        return in_array($email->value(), $this->existing, true);
    }

    public function create(Email $email, Password $password, bool $verified = false): User
    {
        $this->created[] = $email->value();

        $user = new User;
        $user->email = $email->value();
        // В Unit-тесте приложение не поднято: каст hashed недоступен, храним как есть.
        $user->setRawAttributes(['email' => $email->value()], true);

        return $user;
    }
}
