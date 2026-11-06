<?php

namespace Tests\Unit\Modules\User\Application\Commands;

use App\Modules\User\Application\Commands\RegisterUser\RegisterUserCommand;
use App\Modules\User\Application\Commands\RegisterUser\RegisterUserCommandHandler;
use App\Modules\User\Application\Results\RegistrationResult;
use App\Modules\User\Domain\Exceptions\EmailAlreadyRegistered;
use App\Modules\User\Domain\Services\UserRegistrar;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\User\Support\InMemoryUserRepository;

/**
 * Хендлер команды регистрации: создание пользователя и правило уникальности email.
 */
final class RegisterUserCommandHandlerTest extends TestCase
{
    public function test_returns_registered_user(): void
    {
        $repository = new InMemoryUserRepository;
        $handler = new RegisterUserCommandHandler(new UserRegistrar($repository));

        $result = $handler->handle(RegisterUserCommand::fromArray([
            'email' => 'new@example.com',
            'password' => 'secret-password',
        ]));

        self::assertInstanceOf(RegistrationResult::class, $result);
        self::assertSame('new@example.com', $result->user->email);
        self::assertSame(['new@example.com'], $repository->created);
    }

    public function test_command_normalizes_email_and_ignores_device_name(): void
    {
        // Имя устройства — забота auth-адаптера, а не команды регистрации:
        // лишние ключи payload не должны влиять на сценарий.
        $command = RegisterUserCommand::fromArray([
            'email' => '  Device@Example.com ',
            'password' => 'secret-password',
            'device_name' => 'phpunit',
        ]);

        self::assertSame('device@example.com', $command->email->value());
    }

    public function test_rejects_already_registered_email(): void
    {
        $repository = new InMemoryUserRepository;
        $repository->seed('taken@example.com');
        $handler = new RegisterUserCommandHandler(new UserRegistrar($repository));

        $this->expectException(EmailAlreadyRegistered::class);

        try {
            $handler->handle(RegisterUserCommand::fromArray([
                'email' => 'TAKEN@example.com',
                'password' => 'secret-password',
            ]));
        } finally {
            self::assertSame([], $repository->created);
        }
    }
}
