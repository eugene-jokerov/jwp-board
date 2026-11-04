<?php

namespace App\Modules\User\Application\Queries\CurrentUser;

/**
 * Запрос данных текущего аутентифицированного пользователя.
 *
 * Содержит только идентификатор: выборку выполняет хендлер запроса,
 * который через порт репозитория получает актуальную запись из хранилища.
 */
final readonly class CurrentUserQuery
{
    public function __construct(
        public int $userId,
    ) {}
}
