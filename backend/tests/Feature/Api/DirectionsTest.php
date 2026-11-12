<?php

namespace Tests\Feature\Api;

use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Task\Domain\Task;
use App\Modules\User\Domain\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD направлений: /api/v1/directions (модуль Direction).
 */
final class DirectionsTest extends TestCase
{
    use RefreshDatabase;

    private const string ENDPOINT = '/api/v1/directions';

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
    }

    public function test_user_can_create_direction(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, ['title' => '  Здоровье  ', 'color' => 'c3'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Здоровье')
            ->assertJsonPath('data.color', 'c3');

        $this->assertDatabaseHas('directions', [
            'user_id' => $user->id,
            'title' => 'Здоровье',
            'color' => 'c3',
        ]);
    }

    public function test_color_must_be_from_palette(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, ['title' => 'Направление', 'color' => 'c0'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['color']);
    }

    /**
     * Контракт для клиента: 422 раскладывает причину по ключам полей.
     *
     * Форма направления показывает ошибку сервера под самим полем (`errors.title`
     * у названия, `errors.color` у палитры), а не общим сообщением, поэтому
     * ответ обязан называть поля теми же ключами, что и тело запроса.
     */
    public function test_validation_errors_are_addressed_to_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'color']);

        // По ключу на поле — строки, а не общий текст под другим ключом.
        $this->assertSame(
            ['title', 'color'],
            array_keys($response->json('errors')),
        );
    }

    /**
     * Слишком длинное название — ошибка поля `title`, а не общая: форма
     * подсвечивает само поле.
     */
    public function test_long_title_is_addressed_to_title_field(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, ['title' => str_repeat('Я', 256), 'color' => 'c1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title'])
            ->assertJsonMissingValidationErrors(['color']);
    }

    /**
     * PATCH проверяется так же: обе ошибки адресованы своим полям.
     */
    public function test_update_validation_errors_are_addressed_to_fields(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$direction->id, ['title' => str_repeat('Я', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title'])
            ->assertJsonMissingValidationErrors(['color']);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$direction->id, ['color' => 'c0'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['color'])
            ->assertJsonMissingValidationErrors(['title']);
    }

    public function test_index_returns_only_own_directions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Direction::factory()->create(['user_id' => $user->id, 'title' => 'Моё']);
        Direction::factory()->create(['user_id' => $other->id, 'title' => 'Чужое']);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Моё');
    }

    public function test_index_returns_limit_in_meta(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('meta.limit', (int) config('direction.max_per_user'));
    }

    public function test_limit_comes_from_config(): void
    {
        config(['direction.max_per_user' => 3]);

        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('meta.limit', 3);
    }

    public function test_create_is_forbidden_at_limit(): void
    {
        // Лимит задаётся конфигом, поэтому проверка не зависит от значения
        // в backend/.env и не требует создания пятнадцати направлений.
        config(['direction.max_per_user' => 2]);

        $user = User::factory()->create();

        Direction::factory()
            ->count(2)
            ->sequence(fn (Sequence $sequence): array => ['color' => DirectionColor::keys()[$sequence->index]])
            ->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, ['title' => 'Лишнее', 'color' => 'c19'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['direction'])
            ->assertJsonPath('errors.direction.0', 'Достигнут лимит направлений (2). Удалите ненужное направление, чтобы добавить новое.');

        $this->assertDatabaseCount('directions', 2);
    }

    public function test_create_is_allowed_below_limit(): void
    {
        config(['direction.max_per_user' => 2]);

        $user = User::factory()->create();

        Direction::factory()
            ->count(1)
            ->create(['user_id' => $user->id, 'color' => DirectionColor::Blue->value]);

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, ['title' => 'Последнее', 'color' => 'c19'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Последнее');

        $this->assertDatabaseCount('directions', 2);
    }

    public function test_limit_is_counted_per_user(): void
    {
        config(['direction.max_per_user' => 1]);

        $user = User::factory()->create();
        $other = User::factory()->create();

        Direction::factory()->create(['user_id' => $other->id]);

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, ['title' => 'Своё', 'color' => 'c1'])
            ->assertCreated();
    }

    public function test_show_returns_foreign_direction_as_not_found(): void
    {
        $user = User::factory()->create();
        $foreign = Direction::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'/'.$foreign->id)
            ->assertNotFound();
    }

    public function test_update_changes_title_and_color(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create([
            'user_id' => $user->id,
            'title' => 'Старое',
            'color' => DirectionColor::Blue->value,
        ]);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$direction->id, ['title' => 'Новое', 'color' => 'c7'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Новое')
            ->assertJsonPath('data.color', 'c7');
    }

    public function test_delete_is_forbidden_when_direction_has_cards(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);

        Task::factory()->initiative($direction)->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->deleteJson(self::ENDPOINT.'/'.$direction->id)
            ->assertStatus(409);

        $this->assertDatabaseHas('directions', ['id' => $direction->id]);
    }

    public function test_empty_direction_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->deleteJson(self::ENDPOINT.'/'.$direction->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('directions', ['id' => $direction->id]);
    }
}
