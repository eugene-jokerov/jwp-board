<?php

namespace Tests\Feature\Api\Web;

use App\Modules\User\Domain\User;
use App\Modules\User\Infrastructure\Persistence\EloquentUserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Веб-аутентификация: сессия в HttpOnly-cookie вместо Bearer-токена.
 *
 * Stateful-запрос Sanctum распознаёт по заголовку Origin, который должен
 * совпадать с доменом из sanctum.stateful (в phpunit.xml — localhost:8081).
 */
final class WebAuthTest extends TestCase
{
    use RefreshDatabase;

    /** Origin веб-клиента: по нему Sanctum включает сессию и CSRF. */
    private const string ORIGIN = 'http://localhost:8081';

    /** Имя cookie сессии Laravel: Str::snake(APP_NAME) + '_session' — для «JWP Board» это j_w_p_board_session. */
    private const string SESSION_COOKIE = 'j_w_p_board_session';

    /**
     * Вход веб-клиента: stateful-запрос с Origin.
     *
     * @return TestResponse<Response>
     */
    private function webLogin(string $email, string $password): TestResponse
    {
        return $this->withHeader('Origin', self::ORIGIN)
            ->postJson('/api/v1/auth/web/login', [
                'email' => $email,
                'password' => $password,
            ]);
    }

    /**
     * Значение сессионной cookie из ответа на вход.
     *
     * @param  TestResponse<Response>  $response
     */
    private function sessionCookieValue(TestResponse $response): string
    {
        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie): bool => $cookie->getName() === self::SESSION_COOKIE);

        self::assertNotNull($cookie, 'Сессионная cookie не выставлена');
        self::assertTrue($cookie->isHttpOnly(), 'Cookie сессии должна быть HttpOnly');

        return (string) $cookie->getValue();
    }

    public function test_web_login_returns_user_without_token_and_sets_httponly_cookie(): void
    {
        $user = EloquentUserModel::factory()->create(['password' => 'secret-password']);

        $response = $this->webLogin($user->email, 'secret-password');

        $response
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.token_type');

        // Токена в ответе нет: доказательство входа — только сессионная cookie.
        self::assertStringNotContainsString('token', (string) $response->getContent());

        // Веб-клиент получает тот же признак режима, что и мобильный.
        $response->assertJsonPath('data.readonly', false);

        $this->sessionCookieValue($response);
    }

    public function test_web_login_with_wrong_password_returns_unauthorized(): void
    {
        $user = EloquentUserModel::factory()->create();

        $this->webLogin($user->email, 'wrong-password')->assertUnauthorized();
    }

    public function test_web_register_authorizes_immediately_and_creates_no_token(): void
    {
        $response = $this->withHeader('Origin', self::ORIGIN)
            ->postJson('/api/v1/auth/web/register', [
                'email' => 'web-user@jwp-board.local',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.email', 'web-user@jwp-board.local')
            ->assertJsonMissingPath('data.token');

        // После регистрации пользователь сразу авторизован: cookie уже пришла.
        $this->sessionCookieValue($response);

        // Веб-регистрация не создаёт ненужный Bearer-токен.
        $user = User::query()->where('email', 'web-user@jwp-board.local')->firstOrFail();
        self::assertSame(0, $user->tokens()->count());
    }

    public function test_session_cookie_authorizes_web_and_shared_routes(): void
    {
        $user = EloquentUserModel::factory()->create(['password' => 'secret-password']);
        $session = $this->sessionCookieValue($this->webLogin($user->email, 'secret-password'));

        // Восстановление сессии после перезагрузки страницы.
        $this->withUnencryptedCookie(self::SESSION_COOKIE, $session)
            ->getJson('/api/v1/auth/web/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        // Общий роут принимает сессию: auth:sanctum опрашивает web-гвард.
        $this->withUnencryptedCookie(self::SESSION_COOKIE, $session)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_web_logout_destroys_session(): void
    {
        $user = EloquentUserModel::factory()->create(['password' => 'secret-password']);
        $session = $this->sessionCookieValue($this->webLogin($user->email, 'secret-password'));

        $this->withUnencryptedCookie(self::SESSION_COOKIE, $session)
            ->postJson('/api/v1/auth/web/logout')
            ->assertNoContent();

        // Старая cookie после выхода больше не авторизует.
        $this->withUnencryptedCookie(self::SESSION_COOKIE, $session)
            ->getJson('/api/v1/auth/web/me')
            ->assertUnauthorized();
    }

    public function test_web_me_requires_session(): void
    {
        $this->getJson('/api/v1/auth/web/me')->assertUnauthorized();
    }

    public function test_mobile_bearer_login_still_returns_token(): void
    {
        $user = EloquentUserModel::factory()->create(['password' => 'secret-password']);

        // Мобильный клиент не присылает Origin: сессия не стартует, только токен.
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'phpunit',
        ])->assertOk()->assertJsonPath('data.token_type', 'Bearer');

        $this->assertEmpty($response->headers->getCookies());

        $this->withHeader('Authorization', 'Bearer '.$response->json('data.token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }
}
