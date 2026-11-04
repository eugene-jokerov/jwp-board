<?php

namespace Tests\Feature\Api;

use App\Modules\User\Domain\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Регистрация пользователя: POST /api/v1/user/register (модуль User).
 */
final class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private const string ENDPOINT = '/api/v1/user/register';

    public function test_user_can_register_and_receives_token(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'email' => 'new-user@jwp-board.local',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'device_name' => 'phpunit',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'new-user@jwp-board.local')
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'email', 'created_at'],
                ],
            ]);

        self::assertStringNotContainsString('password', (string) $response->getContent());

        $user = User::query()->where('email', 'new-user@jwp-board.local')->firstOrFail();

        self::assertTrue(Hash::check('secret-password', $user->password));
        self::assertNull($user->email_verified_at);

        // Выданный токен сразу рабочий.
        $this->withHeader('Authorization', 'Bearer '.$response->json('data.token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_email_is_normalized_to_lowercase(): void
    {
        $this->postJson(self::ENDPOINT, [
            'email' => '  Mixed.Case@JWP-Board.Local ',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertCreated();

        self::assertDatabaseHas('users', ['email' => 'mixed.case@jwp-board.local']);
    }

    public function test_registration_with_existing_email_returns_validation_error(): void
    {
        User::factory()->create(['email' => 'taken@jwp-board.local']);

        $this->postJson(self::ENDPOINT, [
            'email' => 'TAKEN@jwp-board.local',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        self::assertSame(1, User::query()->count());
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $this->postJson(self::ENDPOINT, [
            'email' => 'no-confirmation@jwp-board.local',
            'password' => 'secret-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password_confirmation']);

        self::assertSame(0, User::query()->count());
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $this->postJson(self::ENDPOINT, [
            'email' => 'mismatch@jwp-board.local',
            'password' => 'secret-password',
            'password_confirmation' => 'another-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        self::assertSame(0, User::query()->count());
    }

    public function test_registration_rejects_short_password(): void
    {
        $this->postJson(self::ENDPOINT, [
            'email' => 'short@jwp-board.local',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_rejects_invalid_email(): void
    {
        $this->postJson(self::ENDPOINT, [
            'email' => 'not-an-email',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_users_table_has_no_name_column(): void
    {
        self::assertFalse(Schema::hasColumn('users', 'name'));
    }
}
