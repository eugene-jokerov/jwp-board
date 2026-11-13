<?php

namespace Tests\Feature\Filament;

use App\Modules\Direction\Infrastructure\Persistence\EloquentDirectionModel;
use App\Modules\History\Infrastructure\Persistence\HistoryRecord;
use App\Modules\Task\Application\Commands\CreateTask\CreateTaskCommand;
use App\Modules\Task\Application\Commands\CreateTaskForAdmin\CreateTaskForAdminCommand;
use App\Modules\Task\Application\Commands\DeleteTaskForAdmin\DeleteTaskForAdminCommand;
use App\Modules\Task\Domain\Exceptions\TaskHasChildren;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;
use App\Modules\Task\Infrastructure\Persistence\EloquentTaskModel;
use App\Modules\User\Domain\User;
use App\Modules\User\Infrastructure\Persistence\EloquentUserModel;
use App\Shared\Application\Bus\CommandBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Команды админки для карточек.
 *
 * Проверяются два сценария удаления из панели: обычное удаление по-прежнему
 * запрещено для карточки с детьми, а каскадное (модерация) вычищает всё
 * поддерево и пишет запись `deleted` на каждую удалённую карточку. Плюс
 * создание карточки с владельцем из формы — администратор задаёт его явно.
 */
final class AdminTaskCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function bus(): CommandBus
    {
        return app(CommandBus::class);
    }

    /**
     * Инициатива → два проекта → по задаче в каждом.
     *
     * @return array{initiative: Task, projects: array<int, Task>, tasks: array<int, Task>}
     */
    private function initiativeTree(User $user): array
    {
        $direction = EloquentDirectionModel::factory()->create(['user_id' => $user->id]);

        $initiative = EloquentTaskModel::factory()->initiative($direction)->create(['user_id' => $user->id]);

        $firstProject = EloquentTaskModel::factory()->project($initiative)->create(['user_id' => $user->id]);
        $secondProject = EloquentTaskModel::factory()->project($initiative)->create(['user_id' => $user->id]);

        $tasks = [
            EloquentTaskModel::factory()->task($firstProject)->create(['user_id' => $user->id]),
            EloquentTaskModel::factory()->task($secondProject)->create(['user_id' => $user->id]),
        ];

        return [
            'initiative' => $initiative,
            'projects' => [$firstProject, $secondProject],
            'tasks' => $tasks,
        ];
    }

    public function test_create_task_for_admin_uses_owner_from_form(): void
    {
        $user = EloquentUserModel::factory()->create();

        $command = new CreateTaskForAdminCommand($user->id, new CreateTaskCommand(
            type: CardType::Task,
            column: CardColumn::Backlog,
            title: TaskTitle::fromString('Карточка из админки'),
        ));

        $result = $this->bus()->dispatch($command);

        $this->assertDatabaseHas('tasks', [
            'id' => $result->id,
            'user_id' => $user->id,
            'title' => 'Карточка из админки',
        ]);
    }

    public function test_delete_without_cascade_is_blocked_for_card_with_children(): void
    {
        $user = EloquentUserModel::factory()->create();
        $tree = $this->initiativeTree($user);

        $this->expectException(TaskHasChildren::class);

        $this->bus()->dispatch(new DeleteTaskForAdminCommand($user->id, (int) $tree['initiative']->id));
    }

    public function test_cascade_delete_removes_whole_subtree(): void
    {
        $user = EloquentUserModel::factory()->create();
        $tree = $this->initiativeTree($user);

        $deleted = $this->bus()->dispatch(new DeleteTaskForAdminCommand(
            $user->id,
            (int) $tree['initiative']->id,
            cascade: true,
        ));

        $this->assertSame(5, $deleted);

        $expected = array_map(
            static fn (Task $task): int => (int) $task->id,
            [$tree['initiative'], ...$tree['projects'], ...$tree['tasks']],
        );

        $this->assertSame(0, Task::query()->whereIn('id', $expected)->count());
        $this->assertSame(0, Task::query()->where('user_id', $user->id)->count());
    }

    public function test_cascade_delete_writes_history_entry_for_each_card(): void
    {
        $user = EloquentUserModel::factory()->create();
        $tree = $this->initiativeTree($user);

        $this->bus()->dispatch(new DeleteTaskForAdminCommand(
            $user->id,
            (int) $tree['initiative']->id,
            cascade: true,
        ));

        $this->assertSame(5, HistoryRecord::query()
            ->where('entity_type', 'task')
            ->where('action', 'delete')
            ->count());
    }

    public function test_cascade_delete_does_not_touch_other_users_cards(): void
    {
        $owner = EloquentUserModel::factory()->create();
        $other = EloquentUserModel::factory()->create();

        $tree = $this->initiativeTree($owner);
        $foreign = EloquentTaskModel::factory()->create(['user_id' => $other->id]);

        $this->bus()->dispatch(new DeleteTaskForAdminCommand(
            $owner->id,
            (int) $tree['initiative']->id,
            cascade: true,
        ));

        $this->assertDatabaseHas('tasks', ['id' => $foreign->id]);
    }
}
