<?php

namespace Tests\Feature\Api;

use App\Modules\Direction\Domain\Direction;
use App\Modules\Task\Domain\Task;
use App\Modules\User\Domain\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD карточек и инварианты иерархии: /api/v1/tasks (модуль Task).
 */
final class TasksTest extends TestCase
{
    use RefreshDatabase;

    private const string ENDPOINT = '/api/v1/tasks';

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

    public function test_strategic_card_requires_direction(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'initiative',
                'column' => 'backlog',
                'title' => 'Инициатива без направления',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hierarchy']);
    }

    public function test_strategic_card_is_created_with_direction(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id, 'color' => 'c5']);

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'initiative',
                'column' => 'backlog',
                'title' => 'Инициатива',
                'direction_id' => $direction->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'initiative')
            ->assertJsonPath('data.direction_id', $direction->id)
            ->assertJsonPath('data.initiative_id', null)
            ->assertJsonPath('data.project_id', null)
            ->assertJsonPath('data.color', 'c5')
            ->assertJsonPath('data.allowed_transitions', ['backlog', 'in_progress', 'complete', 'canceled']);
    }

    public function test_tactical_card_inherits_direction_from_initiative(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);
        $initiative = Task::factory()->initiative($direction)->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'project',
                'column' => 'in_progress',
                'title' => 'Проект',
                'initiative_id' => $initiative->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.initiative_id', $initiative->id)
            ->assertJsonPath('data.direction_id', $initiative->direction_id)
            ->assertJsonPath('data.project_id', null);
    }

    public function test_tactical_card_without_initiative_has_no_direction(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'project',
                'column' => 'backlog',
                'title' => 'Проект без инициативы',
            ])
            ->assertCreated()
            ->assertJsonPath('data.direction_id', null)
            ->assertJsonPath('data.color', null);
    }

    public function test_direction_cannot_be_passed_for_tactical_card(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'project',
                'column' => 'backlog',
                'title' => 'Проект',
                'direction_id' => $direction->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hierarchy']);
    }

    public function test_operational_card_inherits_chain_from_project(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);
        $initiative = Task::factory()->initiative($direction)->create(['user_id' => $user->id]);
        $project = Task::factory()->project($initiative)->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Задача',
                'project_id' => $project->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.initiative_id', $initiative->id)
            ->assertJsonPath('data.direction_id', $initiative->direction_id);
    }

    public function test_operational_card_without_project(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'canceled',
                'title' => 'Задача без проекта',
            ])
            ->assertCreated()
            ->assertJsonPath('data.direction_id', null)
            ->assertJsonPath('data.initiative_id', null)
            ->assertJsonPath('data.project_id', null);
    }

    public function test_user_can_change_title_and_description(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Старое']);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['title' => 'Новое', 'description' => 'Детали'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Новое')
            ->assertJsonPath('data.description', 'Детали');
    }

    public function test_description_is_limited_to_2000_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Задача',
                'description' => str_repeat('a', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    }

    public function test_delete_is_forbidden_when_card_has_children(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);
        $initiative = Task::factory()->initiative($direction)->create(['user_id' => $user->id]);
        Task::factory()->project($initiative)->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->deleteJson(self::ENDPOINT.'/'.$initiative->id)
            ->assertStatus(409);

        $this->assertDatabaseHas('tasks', ['id' => $initiative->id]);
    }

    public function test_card_without_children_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->deleteJson(self::ENDPOINT.'/'.$task->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_foreign_card_is_not_found(): void
    {
        $user = User::factory()->create();
        $foreign = Task::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'/'.$foreign->id)
            ->assertNotFound();

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$foreign->id, ['title' => 'Взлом'])
            ->assertNotFound();
    }

    public function test_index_filters_by_type_and_parent(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);
        $initiative = Task::factory()->initiative($direction)->create(['user_id' => $user->id]);
        $project = Task::factory()->project($initiative)->create(['user_id' => $user->id]);
        Task::factory()->task($project)->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?type=project')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $project->id);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?project_id='.$project->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'task');
    }

    /**
     * Опечатка в фильтре — ошибка клиента, а не тихо отключённый фильтр:
     * иначе доска покажет лишние карточки и баг останется незамеченным.
     */
    public function test_index_rejects_unknown_filter_values(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?type=iniciatives')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?column=done')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['column']);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?direction_id=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['direction_id']);
    }

    public function test_index_without_filters_returns_all_cards(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);
        $initiative = Task::factory()->initiative($direction)->create(['user_id' => $user->id]);
        Task::factory()->project($initiative)->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * Дети наследуют направление и инициативу карточки: отвязка родителя
     * оставила бы у них денормализацию старой ветки.
     */
    public function test_card_with_children_cannot_be_unlinked_from_initiative(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);
        $initiative = Task::factory()->initiative($direction)->create(['user_id' => $user->id]);
        $project = Task::factory()->project($initiative)->create(['user_id' => $user->id]);
        Task::factory()->task($project)->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$project->id, ['initiative_id' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hierarchy']);

        $this->assertDatabaseHas('tasks', [
            'id' => $project->id,
            'initiative_id' => $initiative->id,
            'direction_id' => $direction->id,
        ]);
    }

    public function test_card_with_children_cannot_be_unlinked_from_project(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);
        $initiative = Task::factory()->initiative($direction)->create(['user_id' => $user->id]);
        $project = Task::factory()->project($initiative)->create(['user_id' => $user->id]);
        $task = Task::factory()->task($project)->create(['user_id' => $user->id]);
        Task::factory()->task($task)->create(['user_id' => $user->id]);

        // Задача стала родителем: у неё есть своя задача, хотя по типу она
        // операционная. Дети ищутся по ссылке project_id, поэтому запрет
        // срабатывает до проверки иерархии.
        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['project_id' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hierarchy']);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'project_id' => $project->id]);
    }

    public function test_card_without_children_can_be_unlinked_from_initiative(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);
        $initiative = Task::factory()->initiative($direction)->create(['user_id' => $user->id]);
        $project = Task::factory()->project($initiative)->create(['user_id' => $user->id]);

        // Проект остаётся без инициативы и, значит, без направления:
        // клиент передаёт явный null в direction_id вместе с отвязкой.
        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$project->id, ['initiative_id' => null, 'direction_id' => null])
            ->assertOk()
            ->assertJsonPath('data.initiative_id', null)
            ->assertJsonPath('data.direction_id', null)
            ->assertJsonPath('data.color', null);
    }

    /**
     * Титул из пробелов проходит правило `max`, но не value object:
     * ответ должен остаться 422, а не превратиться в 500.
     */
    public function test_blank_title_is_rejected_on_create_and_update(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, ['type' => 'task', 'column' => 'backlog', 'title' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['title' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }
}
