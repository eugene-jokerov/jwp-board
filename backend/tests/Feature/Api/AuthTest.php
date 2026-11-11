<?php

namespace Tests\Feature\Api;

use App\Modules\User\Domain\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * Тесты аутентификации по токенам Sanctum.
 */
final class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'phpunit',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'email', 'created_at'],
                ],
            ]);

        // Пароль в ответе не должен фигурировать; имени у пользователя больше нет.
        self::assertStringNotContainsString('password', (string) $response->getContent());
        self::assertArrayNotHasKey('name', $response->json('data.user'));
    }

    public function test_login_is_case_insensitive_for_email(): void
    {
        $user = User::factory()->create([
            'email' => 'mixed@jwp-board.local',
            'password' => 'secret-password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'MIXED@jwp-board.local',
            'password' => 'secret-password',
        ])->assertOk()->assertJsonPath('data.user.id', $user->id);
    }

    public function test_login_with_wrong_password_returns_unauthorized(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_login_requires_valid_payload(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_authenticated_user_can_fetch_profile_and_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        // После выхода токен больше не действует.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    /**
     * Токен мобильного клиента живёт год, а не бессрочно: с `expiration` в
     * конфиге Sanctum считает `created_at + срок`.
     */
    public function test_issued_token_expires_in_one_year(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'phpunit',
        ])->assertOk();

        $token = $user->tokens()->firstOrFail();

        self::assertSame(525600, config('sanctum.expiration'));
        self::assertNotNull($token->expires_at);
        // 365 дней = 525600 минут: токен живёт от момента выдачи до года.
        self::assertSame(
            $token->created_at->copy()->addMinutes(525600)->getTimestamp(),
            $token->expires_at->getTimestamp(),
            'Срок жизни выданного токена должен быть равен году.',
        );
        self::assertSame(now()->addYear()->getTimestamp(), $token->expires_at->getTimestamp());
    }

    /**
     * Просроченный токен больше не аутентифицирует: клиент получит 401 и
     * попросит войти заново.
     */
    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phpunit');
        $token->accessToken->forceFill(['expires_at' => now()->subMinute()])->save();
        $plain = $token->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    /**
     * Перебор пароля должен упираться в лимитер, а не в БД.
     */
    public function test_login_is_rate_limited(): void
    {
        // Лимитер считает по IP: 10 попыток в минуту.
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'brute@jwp-board.local',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'brute@jwp-board.local',
            'password' => 'wrong-password',
        ])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    }

    /**
     * У регистрации свой счётчик: исчерпанный лимит попыток входа не должен
     * закрывать регистрацию новому пользователю.
     */
    public function test_registration_is_not_blocked_by_login_throttle(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'brute@jwp-board.local',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'brute@jwp-board.local',
            'password' => 'wrong-password',
        ])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

        $this->postJson('/api/v1/user/register', [
            'email' => 'newcomer@jwp-board.local',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertCreated();
    }
}
