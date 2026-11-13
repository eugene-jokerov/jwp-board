<?php

namespace App\Modules\User\Application\Commands\IssueBearerToken;

use App\Modules\User\Application\Results\IssuedToken;
use App\Modules\User\Domain\Contracts\TokenIssuer;

/**
 * Хендлер выдачи токена: просит у порта персональный токен для пользователя.
 *
 * Сценарий не знает, какие токены выдаёт система (Sanctum со сроком из
 * конфигурации): это решает адаптер канала.
 */
final readonly class IssueBearerTokenCommandHandler
{
    public function __construct(
        private TokenIssuer $tokens,
    ) {}

    public function handle(IssueBearerTokenCommand $command): IssuedToken
    {
        return new IssuedToken($this->tokens->issue($command->user, $command->deviceName));
    }
}
