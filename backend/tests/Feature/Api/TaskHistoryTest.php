<?php

namespace Tests\Feature\Api;

use App\Modules\Direction\Domain\Direction;
use App\Modules\History\Infrastructure\Persistence\HistoryRecord;
use App\Modules\Task\Domain\Task;
use App\Modules\User\Domain\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Журнал изменений: одна запись на изменение, на update — по записи на поле.
 */
final class TaskHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(User $user): self
    {
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token);

        return $this;
    }

    public function test_creating_card_writes_insert_entry(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson('/api/v1/tasks', ['type' => 'task', 'column' => 'backlog', 'title' => 'Задача'])
            ->assertCreated();

        $this->assertDatabaseHas('histories', [
            'entity_type' => 'task',
            'action' => 'insert',
            'user_id' => $user->id,
            'field' => null,
            'old_value' => null,
            'new_value' => null,
        ]);
    }

    public function test_update_writes_one_entry_per_changed_field(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Старое']);

        $this->actingAsUser($user)
            ->patchJson('/api/v1/tasks/'.$task->id, ['title' => 'Новое', 'column' => 'complete'])
            ->assertOk();

        $entries = HistoryRecord::query()
            ->where('entity_id', $task->id)
            ->where('action', 'update')
            ->orderBy('field')
            ->get();

        self::assertCount(2, $entries);

        $column = $entries->firstWhere('field', 'column');
        self::assertNotNull($column);
        self::assertSame('backlog', $column->old_value);
        self::assertSame('complete', $column->new_value);

        $title = $entries->firstWhere('field', 'title');
        self::assertNotNull($title);
        self::assertSame('Старое', $title->old_value);
        self::assertSame('Новое', $title->new_value);
    }

    public function test_description_change_is_logged_without_values(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->patchJson('/api/v1/tasks/'.$task->id, ['description' => 'Очень длинный текст'])
            ->assertOk();

        $entry = HistoryRecord::query()
            ->where('entity_id', $task->id)
            ->where('field', 'description')
            ->firstOrFail();

        self::assertNull($entry->old_value);
        self::assertNull($entry->new_value);
    }

    public function test_unchanged_fields_are_not_logged(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'То же']);

        $this->actingAsUser($user)
            ->patchJson('/api/v1/tasks/'.$task->id, ['title' => 'То же'])
            ->assertOk();

        self::assertSame(0, HistoryRecord::query()->where('action', 'update')->count());
    }

    public function test_deleting_card_writes_delete_entry(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->deleteJson('/api/v1/tasks/'.$task->id)
            ->assertNoContent();

        $this->assertDatabaseHas('histories', [
            'entity_type' => 'task',
            'entity_id' => $task->id,
            'action' => 'delete',
        ]);
    }

    public function test_direction_changes_are_logged(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/directions', ['title' => 'Здоровье', 'color' => 'c1'])
            ->assertCreated();

        $directionId = (int) $response->json('data.id');

        $this->actingAsUser($user)
            ->patchJson('/api/v1/directions/'.$directionId, ['color' => 'c9'])
            ->assertOk();

        $this->assertDatabaseHas('histories', [
            'entity_type' => 'direction',
            'entity_id' => $directionId,
            'action' => 'insert',
        ]);

        $this->assertDatabaseHas('histories', [
            'entity_type' => 'direction',
            'entity_id' => $directionId,
            'action' => 'update',
            'field' => 'color',
            'old_value' => 'c1',
            'new_value' => 'c9',
        ]);

        Direction::query()->whereKey($directionId)->delete();
    }
}
