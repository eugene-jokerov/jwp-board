<?php

namespace App\Modules\User\Domain\Services;

use App\Modules\User\Domain\Contracts\UserRepository;
use App\Modules\User\Domain\Exceptions\EmailAlreadyRegistered;
use App\Modules\User\Domain\User;
use App\Modules\User\Domain\ValueObjects\Email;
use App\Modules\User\Domain\ValueObjects\Password;

/**
 * Доменный сервис регистрации пользователей.
 *
 * Отвечает только за правило предметной области: один email — один пользователь.
 * Хеширование пароля выполняет адаптер (каст `hashed` модели User).
 */
final readonly class UserRegistrar
{
    public function __construct(
        private UserRepository $users,
    ) {}

    /**
     * Зарегистрировать нового пользователя.
     *
     * @throws EmailAlreadyRegistered если email уже занят
     */
    public function register(Email $email, Password $password): User
    {
        if ($this->users->emailExists($email)) {
            throw EmailAlreadyRegistered::forEmail($email->value());
        }

        return $this->users->create($email, $password);
    }
}
