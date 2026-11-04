<?php

namespace Tests\Unit\Modules\User\Application\Queries;

use App\Modules\User\Application\Queries\CurrentUser\CurrentUserQuery;
use App\Modules\User\Application\Queries\CurrentUser\CurrentUserQueryHandler;
use App\Modules\User\Domain\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\User\Support\InMemoryUserRepository;

/**
 * Хендлер запроса текущего пользователя: выборка по id через порт репозитория.
 */
final class CurrentUserQueryHandlerTest extends TestCase
{
    public function test_returns_user_from_repository(): void
    {
        $repository = new InMemoryUserRepository;
        $repository->seed('current@example.com', 7);
        $handler = new CurrentUserQueryHandler($repository);

        $user = $handler->handle(new CurrentUserQuery(7));

        self::assertInstanceOf(User::class, $user);
        self::assertSame('current@example.com', $user->email);
    }

    public function test_propagates_not_found_error(): void
    {
        $handler = new CurrentUserQueryHandler(new InMemoryUserRepository);

        $this->expectException(ModelNotFoundException::class);

        $handler->handle(new CurrentUserQuery(404));
    }
}
