<?php

namespace App\Modules\User\Domain\Contracts;

use App\Modules\User\Domain\User;

/**
 * Проверка пароля пользователя.
 *
 * Домен решает, что пароль верный, но не знает, какой алгоритм хеширования
 * используется: это деталь хранилища. Реализация —
 * Infrastructure\Persistence\EloquentPasswordVerifier (Hash::check).
 */
interface PasswordVerifier
{
    /**
     * Совпадает ли открытый пароль с сохранённым хешем пользователя.
     */
    public function verify(User $user, string $password): bool;
}
