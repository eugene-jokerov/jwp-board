<?php

namespace Tests\Feature\Api;

use App\Modules\User\Domain\User;
use App\Modules\User\Domain\UserMeta;
use App\Modules\User\Infrastructure\Http\Requests\UpdateUserSettingsRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Пользовательские настройки: /api/v1/user/settings (модуль User).
 *
 * Проверяем контракт «ключ — значение»: чтение своих настроек, UPSERT без
 * дублей, изоляцию между пользователями и валидацию ключей.
 */
final class UserSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const string ENDPOINT = '/api/v1/user/settings';

    /**
     * Выдать пользователю Bearer-токен и подставить его в заголовки запросов.
     */
    private function actingAsUser(User $user): self
    {
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token);

        return $this;
    }

    public function test_requires_authentication(): void
    {
        $this->getJson(self::ENDPOINT)->assertUnauthorized();
        $this->putJson(self::ENDPOINT, ['settings' => ['board.theme' => '"dark"']])->assertUnauthorized();
    }

    public function test_index_returns_empty_list_for_new_user(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_update_creates_setting_and_returns_decoded_value(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->putJson(self::ENDPOINT, [
                'settings' => ['board.collapsed_levels' => '{"strategic":true}'],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'board.collapsed_levels')
            ->assertJsonPath('data.0.value.strategic', true);

        $this->assertDatabaseHas('user_meta', [
            'user_id' => $user->id,
            'meta_key' => 'board.collapsed_levels',
            'meta_value' => '{"strategic":true}',
        ]);
    }

    public function test_update_replaces_existing_setting_without_duplicates(): void
    {
        $user = User::factory()->create();

        UserMeta::query()->create([
            'user_id' => $user->id,
            'meta_key' => 'board.collapsed_levels',
            'meta_value' => '{"strategic":true}',
        ]);

        $this->actingAsUser($user)
            ->putJson(self::ENDPOINT, [
                'settings' => ['board.collapsed_levels' => '{}'],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.value', []);

        $this->assertSame(1, UserMeta::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('user_meta', [
            'user_id' => $user->id,
            'meta_key' => 'board.collapsed_levels',
            'meta_value' => '{}',
        ]);
    }

    public function test_update_stores_several_settings_in_one_request(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->putJson(self::ENDPOINT, [
                'settings' => [
                    'board.collapsed_levels' => '{"tactical":true}',
                    'board.collapsed_columns' => '{"strategic":{"backlog":true}}',
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // Порядок пар в ответе задаёт выдача из БД (по ключу), а не порядок
        // запроса, поэтому значения ищем по ключу, а не по индексу.
        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['key' => 'board.collapsed_levels', 'value' => ['tactical' => true]])
            ->assertJsonFragment(['key' => 'board.collapsed_columns', 'value' => ['strategic' => ['backlog' => true]]]);
    }

    public function test_index_returns_only_own_settings(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        UserMeta::query()->create([
            'user_id' => $user->id,
            'meta_key' => 'board.collapsed_levels',
            'meta_value' => '{"strategic":true}',
        ]);
        UserMeta::query()->create([
            'user_id' => $other->id,
            'meta_key' => 'board.collapsed_levels',
            'meta_value' => '{"operational":true}',
        ]);

        $response = $this->actingAsUser($user)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.value.strategic', true);

        // Значение соседа не подмешивается: выборка идёт по user_id.
        self::assertStringNotContainsString('operational', (string) $response->getContent());
    }

    public function test_update_rejects_invalid_key(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->putJson(self::ENDPOINT, [
                'settings' => ['Board Collapsed' => '{}'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings']);
    }

    public function test_update_rejects_invalid_json_value(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->putJson(self::ENDPOINT, [
                'settings' => ['board.collapsed_levels' => '{not json'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.board.collapsed_levels']);
    }

    public function test_update_rejects_value_longer_than_column_in_bytes(): void
    {
        $user = User::factory()->create();

        // Кириллица в UTF-8 — два байта на символ: значение вдвое короче предела
        // в символах уже не влезает в колонку meta_value (TEXT = 65535 байт).
        $value = json_encode(str_repeat('я', 40000), JSON_UNESCAPED_UNICODE);

        self::assertIsString($value);
        self::assertLessThan(UpdateUserSettingsRequest::MAX_VALUE_BYTES, mb_strlen($value));
        self::assertGreaterThan(UpdateUserSettingsRequest::MAX_VALUE_BYTES, strlen($value));

        $this->actingAsUser($user)
            ->putJson(self::ENDPOINT, [
                'settings' => ['board.collapsed_levels' => $value],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.board.collapsed_levels']);

        self::assertSame(0, UserMeta::query()->where('user_id', $user->id)->count());
    }

    public function test_update_accepts_value_within_column_limit(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->putJson(self::ENDPOINT, [
                'settings' => ['board.collapsed_levels' => json_encode(['strategic' => true])],
            ])
            ->assertOk();

        $this->assertDatabaseHas('user_meta', ['user_id' => $user->id, 'meta_key' => 'board.collapsed_levels']);
    }

    public function test_update_requires_non_empty_settings_payload(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->putJson(self::ENDPOINT, ['settings' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings']);
    }

    public function test_destroy_removes_setting(): void
    {
        $user = User::factory()->create();

        UserMeta::query()->create([
            'user_id' => $user->id,
            'meta_key' => 'board.collapsed_levels',
            'meta_value' => '{"strategic":true}',
        ]);

        $this->actingAsUser($user)
            ->deleteJson(self::ENDPOINT.'/board.collapsed_levels')
            ->assertNoContent();

        $this->assertDatabaseMissing('user_meta', [
            'user_id' => $user->id,
            'meta_key' => 'board.collapsed_levels',
        ]);
    }

    public function test_destroy_is_idempotent(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->deleteJson(self::ENDPOINT.'/board.collapsed_levels')
            ->assertNoContent();
    }

    public function test_destroy_does_not_touch_foreign_setting(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        UserMeta::query()->create([
            'user_id' => $other->id,
            'meta_key' => 'board.collapsed_levels',
            'meta_value' => '{"strategic":true}',
        ]);

        $this->actingAsUser($user)
            ->deleteJson(self::ENDPOINT.'/board.collapsed_levels')
            ->assertNoContent();

        $this->assertDatabaseHas('user_meta', [
            'user_id' => $other->id,
            'meta_key' => 'board.collapsed_levels',
        ]);
    }

    public function test_settings_are_deleted_with_user(): void
    {
        $user = User::factory()->create();

        UserMeta::query()->create([
            'user_id' => $user->id,
            'meta_key' => 'board.collapsed_levels',
            'meta_value' => '{"strategic":true}',
        ]);

        $user->delete();

        $this->assertDatabaseMissing('user_meta', ['user_id' => $user->id]);
    }
}
