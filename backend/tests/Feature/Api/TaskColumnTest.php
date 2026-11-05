<?php

namespace Tests\Feature\Api;

use App\Modules\Direction\Domain\Direction;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\User\Domain\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Правила смены колонки: PATCH /api/v1/tasks/{task} с полем column.
 */
final class TaskColumnTest extends TestCase
{
    use RefreshDatabase;

    private const string ENDPOINT = '/api/v1/tasks';

    private function actingAsUser(User $user): self
    {
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token);

        return $this;
    }

    /**
     * Инициатива пользователя с направлением.
     */
    private function initiative(User $user): Task
    {
        $direction = Direction::factory()->create(['user_id' => $user->id]);

        return Task::factory()->initiative($direction)->create(['user_id' => $user->id]);
    }

    /**
     * Операционный уровень: дети отсутствуют, правило не действует.
     */
    public function test_operational_card_can_move_to_any_column(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->inColumn(CardColumn::InProgress)->create(['user_id' => $user->id]);

        foreach ([CardColumn::Backlog, CardColumn::Complete, CardColumn::Canceled] as $column) {
            $this->actingAsUser($user)
                ->patchJson(self::ENDPOINT.'/'.$task->id, ['column' => $column->value])
                ->assertOk()
                ->assertJsonPath('data.column', $column->value);
        }
    }

    /**
     * Тактический уровень: задача проекта в работе блокирует смену колонки.
     */
    public function test_tactical_card_is_blocked_by_task_in_progress(): void
    {
        $user = User::factory()->create();
        $initiative = $this->initiative($user);
        $project = Task::factory()->project($initiative)->inColumn(CardColumn::InProgress)->create(['user_id' => $user->id]);
        $child = Task::factory()->task($project)->inColumn(CardColumn::InProgress)->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$project->id, ['column' => CardColumn::Complete->value])
            ->assertStatus(409);

        // Список доступных переходов в выдаче тоже сужен до текущей колонки.
        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'/'.$project->id)
            ->assertOk()
            ->assertJsonPath('data.allowed_transitions', ['in_progress']);

        // Дочерняя карточка операционного уровня переводится свободно.
        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$child->id, ['column' => CardColumn::Complete->value])
            ->assertOk();

        // После завершения задачи проект разблокирован.
        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$project->id, ['column' => CardColumn::Complete->value])
            ->assertOk()
            ->assertJsonPath('data.column', 'complete');
    }

    /**
     * Стратегический уровень: проект инициативы в работе блокирует смену колонки.
     */
    public function test_strategic_card_is_blocked_by_project_in_progress(): void
    {
        $user = User::factory()->create();
        $initiative = $this->initiative($user);
        $initiative->update(['column' => CardColumn::InProgress->value]);

        Task::factory()->project($initiative)->inColumn(CardColumn::InProgress)->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$initiative->id, ['column' => CardColumn::Backlog->value])
            ->assertStatus(409);
    }

    /**
     * Дети в других колонках (не в работе) смену колонки не блокируют.
     */
    public function test_children_outside_in_progress_do_not_block(): void
    {
        $user = User::factory()->create();
        $initiative = $this->initiative($user);
        $initiative->update(['column' => CardColumn::InProgress->value]);

        Task::factory()->project($initiative)->inColumn(CardColumn::Backlog)->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$initiative->id, ['column' => CardColumn::Complete->value])
            ->assertOk()
            ->assertJsonPath('data.column', 'complete');
    }

    /**
     * Запрет смены колонки блокирует весь PATCH, включая название.
     */
    public function test_blocked_column_change_rolls_back_other_fields(): void
    {
        $user = User::factory()->create();
        $initiative = $this->initiative($user);
        $initiative->update(['column' => CardColumn::InProgress->value]);

        Task::factory()->project($initiative)->inColumn(CardColumn::InProgress)->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$initiative->id, [
                'title' => 'Должно откатиться',
                'column' => CardColumn::Complete->value,
            ])
            ->assertStatus(409);

        $this->assertDatabaseMissing('tasks', ['title' => 'Должно откатиться']);
    }

    public function test_unknown_column_is_rejected(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['column' => 'done'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['column']);
    }
}
