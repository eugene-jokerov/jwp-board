<?php

namespace App\Modules\User\Infrastructure\Persistence;

use App\Modules\User\Domain\Contracts\PasswordVerifier;
use App\Modules\User\Domain\User;
use Illuminate\Support\Facades\Hash;

/**
 * Проверка пароля по хешу, который хранит Eloquent-модель.
 *
 * Алгоритм хеширования — деталь хранилища (каст `hashed` модели), поэтому
 * сверка живёт в инфраструктуре, а не в доменном сервисе.
 */
final readonly class EloquentPasswordVerifier implements PasswordVerifier
{
    public function verify(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }
}
