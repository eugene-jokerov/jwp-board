<?php

namespace App\Modules\User\Application\UseCases;

use App\Modules\User\Application\Commands\RegisterUserCommand;
use App\Modules\User\Application\Results\RegistrationResult;
use App\Modules\User\Domain\Exceptions\EmailAlreadyRegistered;
use App\Modules\User\Domain\Services\UserRegistrar;

/**
 * Сценарий регистрации: создать пользователя.
 *
 * Токен не выдаётся: после регистрации мобильный клиент получает Bearer-токен,
 * а веб-клиент — сессионную cookie. Выбор канала делается в auth-адаптерах.
 */
final readonly class RegisterUserUseCase
{
    public function __construct(
        private UserRegistrar $registrar,
    ) {}

    /**
     * Зарегистрировать пользователя по команде.
     *
     * @throws EmailAlreadyRegistered если email уже занят
     */
    public function execute(RegisterUserCommand $command): RegistrationResult
    {
        $user = $this->registrar->register($command->email, $command->password);

        return new RegistrationResult($user);
    }
}
