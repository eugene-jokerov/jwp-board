<?php

namespace App\Modules\User\Application\Commands\LoginUser;

use App\Modules\User\Application\Results\LoginResult;
use App\Modules\User\Domain\Contracts\PasswordVerifier;
use App\Modules\User\Domain\Contracts\TokenIssuer;
use App\Modules\User\Domain\Contracts\UserRepository;

/**
 * Хендлер входа: найти пользователя и сверить пароль.
 *
 * Возвращает null и на «пользователь не найден», и на «неверный пароль»:
 * вызывающая сторона не должна различать эти случаи в ответе.
 */
final readonly class LoginUserCommandHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordVerifier $passwords,
        private TokenIssuer $tokens,
    ) {}

    /**
     * Проверить учётные данные и вернуть пользователя с токеном.
     */
    public function handle(LoginUserCommand $command): ?LoginResult
    {
        $user = $this->users->findByEmail($command->email);

        if ($user === null) {
            return null;
        }

        if (! $this->passwords->verify($user, $command->password)) {
            return null;
        }

        return new LoginResult($user, $this->tokens->issue($user, $command->deviceName));
    }
}
