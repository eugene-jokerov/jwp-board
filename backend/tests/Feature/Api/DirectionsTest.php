<?php

namespace Tests\Feature\Api;

use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Task\Domain\Task;
use App\Modules\User\Domain\User;
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
