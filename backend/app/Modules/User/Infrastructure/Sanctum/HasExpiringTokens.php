<?php

namespace App\Modules\User\Infrastructure\Sanctum;

use App\Modules\User\Domain\User;
use DateTimeInterface;
use Laravel\Sanctum\NewAccessToken;

/**
 * Выдача персональных токенов с ограниченным сроком жизни.
 *
 * Мобильный клиент живёт на Bearer-токенах. Sanctum по умолчанию создаёт токен
 * без `expires_at`, а срок из `config('sanctum.expiration')` учитывается только
 * при проверке. Здесь он проставляется явно, чтобы срок жизни был виден и в
 * базе, и в админке.
 *
 * Срок — политика адаптера, а не домена: для мобильного клиента это год,
 * `null` в конфиге означает бессрочный токен.
 */
final readonly class HasExpiringTokens
{
    /**
     * Выдать токен пользователю.
     *
     * @param  array<int, string>  $abilities
     */
    public function issue(User $user, string $name, array $abilities = ['*'], ?DateTimeInterface $expiresAt = null): NewAccessToken
    {
        $expiration = config('sanctum.expiration');

        $expiresAt ??= is_numeric($expiration) ? now()->addMinutes((int) $expiration) : null;

        return $user->createToken($name, $abilities, $expiresAt);
    }
}
