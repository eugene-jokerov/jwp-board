<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\DenyReadOnlyWrites;
use App\Modules\User\Domain\Exceptions\ReadOnlyMode;
use App\Modules\User\Domain\User;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Unit-тест middleware `DenyReadOnlyWrites` — без БД и без роутинга.
 *
 * Feature-тесты проверяют режим через реальные маршруты, но тогда отказ может
 * прийти и от контроллера, и от `auth:sanctum`. Здесь middleware вызывается
 * напрямую: видно ровно то, что он решает, — пропустить запрос или бросить
 * доменное исключение.
 */
final class DenyReadOnlyWritesTest extends TestCase
{
    /**
     * Запрос с заданным методом и путём; пользователь — в резолвере запроса.
     */
    private function requestWith(string $method, string $uri, ?User $user): Request
    {
        $request = Request::create($uri, $method);
        $request->setUserResolver(fn (): ?User => $user);

        return $request;
    }

    /**
     * Выполнить middleware и вернуть «вызван ли следующий слой».
     *
     * @throws ReadOnlyMode если middleware запретил запрос
     */
    private function passes(Request $request): bool
    {
        $called = false;

        (new DenyReadOnlyWrites)->handle($request, function () use (&$called): Response {
            $called = true;

            return new Response('ok');
        });

        return $called;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function readMethodProvider(): array
    {
        return [
            'GET' => ['GET'],
            'HEAD' => ['HEAD'],
            'OPTIONS' => ['OPTIONS'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function writeMethodProvider(): array
    {
        return [
            'POST' => ['POST'],
            'PUT' => ['PUT'],
            'PATCH' => ['PATCH'],
            'DELETE' => ['DELETE'],
        ];
    }

    /**
     * Методы чтения проходят при любом пользователе — режим их не касается.
     */
    #[DataProvider('readMethodProvider')]
    public function test_read_methods_always_pass(string $method): void
    {
        self::assertTrue($this->passes($this->requestWith(
            $method,
            '/api/v1/tasks',
            User::factory()->make(['readonly' => true]),
        )));
    }

    /**
     * Любой изменяющий метод read-only пользователя отвергается.
     */
    #[DataProvider('writeMethodProvider')]
    public function test_write_methods_are_denied_for_readonly_user(string $method): void
    {
        $this->expectException(ReadOnlyMode::class);

        $this->passes($this->requestWith(
            $method,
            '/api/v1/tasks',
            User::factory()->make(['readonly' => true]),
        ));
    }

    /**
     * Обычный пользователь пишет без ограничений.
     */
    #[DataProvider('writeMethodProvider')]
    public function test_write_methods_pass_for_regular_user(string $method): void
    {
        self::assertTrue($this->passes($this->requestWith(
            $method,
            '/api/v1/tasks',
            User::factory()->make(['readonly' => false]),
        )));
    }

    /**
     * Гость (без пользователя в запросе) пропускается: 401 — дело
     * auth:sanctum, а не этого middleware.
     */
    #[DataProvider('writeMethodProvider')]
    public function test_guest_is_passed_through(string $method): void
    {
        self::assertTrue($this->passes($this->requestWith($method, '/api/v1/tasks', null)));
    }

    /**
     * Оба канала выхода разрешены: пользователь должен иметь возможность
     * закрыть сессию, а данные при этом не меняются.
     */
    public function test_logout_paths_are_allowed(): void
    {
        $user = User::factory()->make(['readonly' => true]);

        self::assertTrue($this->passes($this->requestWith('POST', '/api/v1/auth/logout', $user)));
        self::assertTrue($this->passes($this->requestWith('POST', '/api/v1/auth/web/logout', $user)));
    }

    /**
     * Похожие пути под исключение не попадают: сравнение идёт по полному
     * адресу, а не по префиксу.
     */
    #[DataProvider('nearMissPathProvider')]
    public function test_only_exact_logout_paths_are_allowed(string $uri): void
    {
        $this->expectException(ReadOnlyMode::class);

        $this->passes($this->requestWith('POST', $uri, User::factory()->make(['readonly' => true])));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nearMissPathProvider(): array
    {
        return [
            'вход по токену' => ['/api/v1/auth/login'],
            'вход веб-канала' => ['/api/v1/auth/web/login'],
            'регистрация веб-канала' => ['/api/v1/auth/web/register'],
            'регистрация' => ['/api/v1/user/register'],
            'выход с вложенным путём' => ['/api/v1/auth/logout/extra'],
            'выход без префикса api' => ['/auth/logout'],
        ];
    }

    /**
     * Флаг приводится к bool: в базе это 0/1, у «старых» записей — null.
     *
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function flagProvider(): array
    {
        return [
            'null — режима нет' => [null, false],
            '0 — режима нет' => [0, false],
            'false — режима нет' => [false, false],
            '1 — режим включён' => [1, true],
            'true — режим включён' => [true, true],
        ];
    }

    #[DataProvider('flagProvider')]
    public function test_flag_values_are_cast_to_boolean(mixed $flag, bool $expectsDenial): void
    {
        $request = $this->requestWith('POST', '/api/v1/directions', User::factory()->make(['readonly' => $flag]));

        if (! $expectsDenial) {
            self::assertTrue($this->passes($request));

            return;
        }

        $this->expectException(ReadOnlyMode::class);

        $this->passes($request);
    }
}
