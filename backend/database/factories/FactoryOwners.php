<?php

namespace Database\Factories;

use App\Modules\User\Domain\Contracts\UserRepository;
use App\Modules\User\Domain\ValueObjects\Email;
use App\Modules\User\Domain\ValueObjects\Password;

/**
 * Владелец демо-данных для фабрик модулей.
 *
 * Фабрики модулей — такой же демо-код, как сиды: им нужен пользователь,
 * а это соседний модуль. Создание идёт через доменный порт UserRepository,
 * поэтому чужая модель хранилища здесь не появляется. Демо-данные — дело
 * database/, поэтому общий помощник живёт рядом с фабриками и сидами.
 */
final class FactoryOwners
{
    /**
     * Пользователь, созданный через доменный порт модуля User.
     */
    public static function user(): int
    {
        $users = app(UserRepository::class);

        return (int) $users->create(
            Email::fromString('owner-'.uniqid().'@jwp-board.local'),
            Password::fromString('password'),
            true,
        )->getKey();
    }
}
