<?php

namespace App\Modules\User\Application\Commands\RegisterUser;

use App\Modules\User\Domain\ValueObjects\Email;
use App\Modules\User\Domain\ValueObjects\Password;

/**
 * Команда регистрации пользователя.
 *
 * Данные уже провалидированы на входе (FormRequest), но дополнительно
 * проходят через value object'ы: они отсекают некорректные значения
 * на границе прикладного слоя.
 *
 * Имя устройства сюда не входит: оно нужно только для выдачи Bearer-токена
 * и решается в auth-адаптере (AuthService), а не в сценарии регистрации.
 */
final readonly class RegisterUserCommand
{
    public function __construct(
        public Email $email,
        public Password $password,
    ) {}

    /**
     * Собрать команду из провалидированных данных запроса.
     *
     * @param  array{email: string, password: string}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            Email::fromString($payload['email']),
            Password::fromString($payload['password']),
        );
    }
}
