<?php

namespace App\Modules\User\Infrastructure\Sanctum;

use App\Modules\User\Domain\Contracts\TokenIssuer;
use App\Modules\User\Domain\User;

/**
 * Адаптер порта TokenIssuer: персональные токены Sanctum.
 */
final readonly class SanctumTokenIssuer implements TokenIssuer
{
    public function __construct(
        private HasExpiringTokens $tokens,
    ) {}

    public function issue(User $user, string $name): string
    {
        return $this->tokens->issue($user, $name)->plainTextToken;
    }
}
