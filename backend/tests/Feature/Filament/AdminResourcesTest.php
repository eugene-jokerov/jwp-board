<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Directions\DirectionResource;
use App\Filament\Resources\Directions\Pages\CreateDirection;
use App\Filament\Resources\Directions\Pages\EditDirection;
use App\Filament\Resources\Directions\Pages\ListDirections;
use App\Filament\Resources\Directions\Pages\ViewDirection;
use App\Filament\Resources\Directions\RelationManagers\TasksRelationManager;
use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\Pages\ViewTask;
use App\Filament\Resources\Tasks\TaskResource;
use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Infrastructure\Persistence\EloquentDirectionModel;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Infrastructure\Persistence\EloquentTaskModel;
use App\Modules\User\Domain\User;
use App\Modules\User\Infrastructure\Persistence\EloquentUserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Разделы «Направления» и «Карточки» в админке открываются и отдают данные.
 *
 * Тест ловит поломки конфигурации ресурсов (формы, таблицы, связи), которые
 * иначе проявились бы только браузером: например, несуществующая связь в
 * Select или сортировка по несуществующей колонке.
 */
final class AdminResourcesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = EloquentUserModel::factory()->admin()->create();

        $this->actingAs($admin, 'web');

        return $admin;
    }

    /**
     * Карточка, у которой заполнены все три ссылки иерархии.
     *
     * @return array{initiative: Task, project: Task, task: Task, direction: Direction}
     */
    private function taskWithParents(User $owner): array
    {
        $direction = EloquentDirectionModel::factory()->create(['user_id' => $owner->id]);
        $initiative = EloquentTaskModel::factory()->initiative($direction)->create(['user_id' => $owner->id]);
        $project = EloquentTaskModel::factory()->project($initiative)->create(['user_id' => $owner->id]);
        $task = EloquentTaskModel::factory()->task($project)->create(['user_id' => $owner->id]);

        return [
            'initiative' => $initiative,
            'project' => $project,
            'task' => $task,
            'direction' => $direction,
        ];
    }

    public function test_directions_list_renders(): void
    {
        $admin = $this->actingAsAdmin();
        EloquentDirectionModel::factory()->create(['user_id' => $admin->id, 'title' => 'Здоровье']);

        Livewire::test(ListDirections::class)
            ->assertSuccessful()
            ->assertSee('Здоровье');
    }

    public function test_direction_create_page_renders(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateDirection::class)->assertSuccessful();
    }

    public function test_direction_edit_page_renders(): void
    {
        $admin = $this->actingAsAdmin();
        $direction = EloquentDirectionModel::factory()->create(['user_id' => $admin->id, 'title' => 'Работа']);

        Livewire::test(EditDirection::class, ['record' => $direction->id])
            ->assertSuccessful()
            ->assertSchemaStateSet(['title' => 'Работа']);
    }

    public function test_tasks_list_renders(): void
    {
        $admin = $this->actingAsAdmin();
        EloquentTaskModel::factory()->create(['user_id' => $admin->id, 'title' => 'Проверить доску']);

        Livewire::test(ListTasks::class)
            ->assertSuccessful()
            ->assertSee('Проверить доску');
    }

    public function test_task_create_page_renders(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateTask::class)->assertSuccessful();
    }

    public function test_tasks_list_has_no_direction_filter(): void
    {
        // Направлений у разных пользователей может быть очень много, поэтому
        // в фильтрах карточек их нет — только тип, колонка, приоритет, идея и владелец.
        $this->actingAsAdmin();

        Livewire::test(ListTasks::class)
            ->assertSuccessful()
            ->assertTableFilterExists('type')
            ->assertTableFilterExists('column')
            ->assertTableFilterExists('priority')
            ->assertTableFilterExists('user_id')
            ->assertDontSee('direction_id');
    }

    public function test_task_view_page_renders_with_linked_hierarchy(): void
    {
        $admin = $this->actingAsAdmin();
        $tree = $this->taskWithParents($admin);

        // Ссылки в блоке «Иерархия» ведут на просмотр родителя: инициатива и
        // проект — в ресурс карточек, направление — в ресурс направлений.
        Livewire::test(ViewTask::class, ['record' => $tree['task']->id])
            ->assertSuccessful()
            ->assertSee(TaskResource::getUrl('view', ['record' => $tree['project']->id]), escape: false)
            ->assertSee(TaskResource::getUrl('view', ['record' => $tree['initiative']->id]), escape: false)
            ->assertSee(DirectionResource::getUrl('view', ['record' => $tree['direction']->id]), escape: false);
    }

    public function test_direction_view_lists_tasks_with_linked_ids(): void
    {
        $admin = $this->actingAsAdmin();
        $tree = $this->taskWithParents($admin);

        // RelationManager рендерится на вкладке «Карточки» внутри страницы
        // направления, поэтому проверяется напрямую, без обвязки вкладок.
        Livewire::test(TasksRelationManager::class, [
            'ownerRecord' => $tree['direction'],
            'pageClass' => ViewDirection::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$tree['task']])
            ->assertSee(TaskResource::getUrl('view', ['record' => $tree['task']->id]), escape: false);
    }

    public function test_direction_tasks_block_is_hidden_on_edit_page(): void
    {
        $admin = $this->actingAsAdmin();
        $tree = $this->taskWithParents($admin);

        // Блок «Карточки» относится к просмотру направления, а в форме
        // редактирования он не выводится вовсе.
        $this->assertTrue(TasksRelationManager::canViewForRecord($tree['direction'], ViewDirection::class));
        $this->assertFalse(TasksRelationManager::canViewForRecord($tree['direction'], EditDirection::class));

        Livewire::test(EditDirection::class, ['record' => $tree['direction']->id])
            ->assertSuccessful()
            ->assertDontSee('Карточки');
    }

    public function test_task_edit_page_renders(): void
    {
        $admin = $this->actingAsAdmin();
        $task = EloquentTaskModel::factory()->create(['user_id' => $admin->id, 'title' => 'Задача в админке']);

        Livewire::test(EditTask::class, ['record' => $task->id])
            ->assertSuccessful()
            ->assertSchemaStateSet(['title' => 'Задача в админке']);
    }
}
