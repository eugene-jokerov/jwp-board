<?php

namespace App\Modules\User\Application\Results;

/**
 * Выданный Bearer-токен.
 *
 * Токен существует только в момент выдачи (Sanctum хранит его хеш),
 * поэтому приходит в результате сценария и сразу уходит в ответ API.
 */
final readonly class IssuedToken
{
    public function __construct(
        public string $plainTextToken,
    ) {}
}
