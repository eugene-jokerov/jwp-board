<?php

namespace App\Modules\User\Domain\Contracts;

use App\Modules\User\Domain\User;
use App\Modules\User\Domain\ValueObjects\Email;
use App\Modules\User\Domain\ValueObjects\Password;

/**
 * Порт доступа к хранилищу пользователей.
 *
 * Домен описывает, что ему нужно от хранилища, не зная про Eloquent.
 * Реализация — Infrastructure\Persistence\EloquentUserRepository.
 */
interface UserRepository
{
    /**
     * Найти пользователя по email (адрес уже нормализован value object'ом).
     */
    public function findByEmail(Email $email): ?User;

    /**
     * Проверить, занят ли email.
     */
    public function emailExists(Email $email): bool;

    /**
     * Создать пользователя с указанными email и паролем.
     *
     * @param  bool  $verified  проставлять ли отметку подтверждения email
     */
    public function create(Email $email, Password $password, bool $verified = false): User;
}
