<?php

namespace App\Modules\User\Application\Queries\CurrentUser;

use App\Modules\User\Domain\Contracts\UserRepository;
use App\Modules\User\Domain\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Хендлер запроса `auth/me`: вернуть пользователя по идентификатору.
 *
 * Чтение идёт через доменный порт, поэтому прикладной слой не зависит
 * от Eloquent. Пользователь мог быть удалён между аутентификацией и
 * запросом — тогда хендлер бросает ModelNotFoundException (404 в API).
 */
final readonly class CurrentUserQueryHandler
{
    public function __construct(
        private UserRepository $users,
    ) {}

    /**
     * @throws ModelNotFoundException если пользователь отсутствует в хранилище
     */
    public function handle(CurrentUserQuery $query): User
    {
        return $this->users->findById($query->userId);
    }
}
