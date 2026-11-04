<?php

namespace App\Services;

use App\Modules\User\Domain\Contracts\UserRepository;
use App\Modules\User\Domain\User;
use App\Modules\User\Domain\ValueObjects\Email;
use Illuminate\Support\Facades\Hash;

/**
 * Аутентификация пользователя по email и паролю.
 *
 * Слой плоский (не гексагон): он переиспользует доменный порт модуля User
 * для доступа к хранилищу, а сверку пароля выполняет сам. Регистрация сюда
 * не входит — это ответственность модуля User (POST /api/v1/user/register).
 */
final readonly class AuthService
{
    public function __construct(
        private UserRepository $users,
    ) {}

    /**
     * Проверить учётные данные и вернуть пользователя.
     *
     * Возвращает null и на «пользователь не найден», и на «неверный пароль»:
     * вызывающая сторона не должна различать эти случаи в ответе.
     */
    public function attempt(Email $email, string $password): ?User
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            return null;
        }

        return Hash::check($password, $user->password) ? $user : null;
    }

    /**
     * Прежний формат ответа: plain-text токен для мобильного клиента.
     */
    public function issueToken(User $user, string $deviceName): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }

    /**
     * Отозвать токен, которым аутентифицирован текущий запрос.
     */
    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
