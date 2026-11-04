<?php

namespace App\Modules\User\Application\Commands;

use App\Modules\User\Domain\ValueObjects\Email;
use App\Modules\User\Domain\ValueObjects\Password;

/**
 * Команда регистрации пользователя.
 *
 * Данные уже провалидированы на входе (FormRequest), но дополнительно
 * проходят через value object'ы: они отсекают некорректные значения
 * на границе прикладного слоя.
 */
final readonly class RegisterUserCommand
{
    public function __construct(
        public Email $email,
        public Password $password,
        public string $deviceName = 'mobile',
    ) {}

    /**
     * Собрать команду из провалидированных данных запроса.
     *
     * @param  array{email: string, password: string, device_name?: string|null}  $payload
     */
    public static function fromArray(array $payload): self
    {
        $deviceName = trim((string) ($payload['device_name'] ?? ''));

        return new self(
            Email::fromString($payload['email']),
            Password::fromString($payload['password']),
            $deviceName === '' ? 'mobile' : $deviceName,
        );
    }
}
