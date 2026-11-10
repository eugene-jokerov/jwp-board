<?php

namespace Tests\Feature\Api;

use App\Modules\Direction\Domain\Direction;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardPriority;
use App\Modules\User\Domain\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    /**
     * Детальный просмотр отдаёт полный набор полей, включая описание.
     */
    public function test_show_returns_full_card_with_description(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id, 'color' => 'c7']);
        $initiative = Task::factory()->initiative($direction)->create(['user_id' => $user->id]);
        $project = Task::factory()->project($initiative)->create([
            'user_id' => $user->id,
            'title' => 'Проект',
            'description' => 'Детали проекта',
        ]);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'/'.$project->id)
            ->assertOk()
            ->assertJsonPath('data.id', $project->id)
            ->assertJsonPath('data.type', 'project')
            ->assertJsonPath('data.title', 'Проект')
            ->assertJsonPath('data.description', 'Детали проекта')
            ->assertJsonPath('data.direction_id', $initiative->direction_id)
            ->assertJsonPath('data.initiative_id', $initiative->id)
            ->assertJsonPath('data.project_id', null)
            ->assertJsonPath('data.color', 'c7')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'column',
                    'title',
                    'slug',
                    'description',
                    'deadline',
                    'direction_id',
                    'initiative_id',
                    'project_id',
                    'color',
                    'allowed_transitions',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    /**
     * Список доски описания не отдаёт: оно может занимать до 2000 символов,
     * а карточек в ответе сотни. Полные данные — в GET /tasks/{task}.
     */
    public function test_index_does_not_return_description(): void
    {
        $user = User::factory()->create();
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Задача',
            'description' => 'Длинное описание',
        ]);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            // Остальные поля карточки на месте — выпало только описание.
            ->assertJsonPath('data.0.title', 'Задача')
            ->assertJsonMissingPath('data.0.description');
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

    public function test_deadline_is_stored_and_returned_as_date(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Задача со сроком',
                'deadline' => '2026-12-31',
            ])
            ->assertCreated()
            // Календарь присылает только дату: время хранится как начало дня.
            ->assertJsonPath('data.deadline', '2026-12-31T00:00:00+03:00');

        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'deadline' => '2026-12-31 00:00:00']);
    }

    public function test_deadline_is_optional(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Задача без срока',
            ])
            ->assertCreated()
            ->assertJsonPath('data.deadline', null);
    }

    public function test_deadline_can_be_changed_and_cleared(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['deadline' => '2027-01-15'])
            ->assertOk()
            ->assertJsonPath('data.deadline', '2027-01-15T00:00:00+03:00');

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['deadline' => null])
            ->assertOk()
            ->assertJsonPath('data.deadline', null);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'deadline' => null]);
    }

    public function test_patch_without_deadline_keeps_it(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->withDeadline(Carbon::parse('2027-02-01 00:00:00'))->create([
            'user_id' => $user->id,
        ]);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['title' => 'Новое название'])
            ->assertOk()
            ->assertJsonPath('data.deadline', '2027-02-01T00:00:00+03:00');
    }

    public function test_invalid_deadline_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Задача',
                'deadline' => '31.12.2026',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deadline']);
    }

    /**
     * Формат валиден, но такого дня нет: отказ тоже 422, а не 500.
     */
    public function test_nonexistent_deadline_date_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Задача',
                'deadline' => '2026-02-30',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deadline']);
    }

    /**
     * Дедлайн нужен списку доски: по нему плитка карточки рисует дату.
     */
    public function test_index_returns_deadline(): void
    {
        $user = User::factory()->create();
        Task::factory()->withDeadline(Carbon::parse('2027-03-05 00:00:00'))->create([
            'user_id' => $user->id,
            'title' => 'Задача со сроком',
        ]);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.deadline', '2027-03-05T00:00:00+03:00');
    }

    public function test_slug_is_stored_and_returned(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Задача с меткой',
                'slug' => '  LAUNCH  ',
            ])
            ->assertCreated()
            // Пробелы по краям срезаются: в базе остаётся само кодовое название.
            ->assertJsonPath('data.slug', 'LAUNCH');

        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'slug' => 'LAUNCH']);
    }

    public function test_slug_is_optional(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Задача без метки',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', null);
    }

    /**
     * Ограничений по символам нет: принимаются любые 16 символов, а 17 — уже нет.
     */
    public function test_slug_is_limited_to_16_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Метка максимальной длины',
                'slug' => str_repeat('Я', 16),
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', str_repeat('Я', 16));

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Слишком длинная метка',
                'slug' => str_repeat('a', 17),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    /**
     * Лимит считается в символах, а не в байтах: 16 кириллических символов
     * (32 байта) проходят наравне с 16 латинскими.
     */
    public function test_slug_length_counts_characters_not_bytes(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Кириллическая метка',
                'slug' => 'АБВГДЕЁЖЗИЙКЛМНО',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'АБВГДЕЁЖЗИЙКЛМНО');

        // Спецсимволы и пробелы внутри тоже допустимы: ограничений по символам нет.
        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Метка со спецсимволами',
                'slug' => 'a b!@#$%^&*()_+',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'a b!@#$%^&*()_+');
    }

    /**
     * Уникальность не проверяется: две карточки с одинаковым slug создаются.
     */
    public function test_slug_duplicates_are_allowed(): void
    {
        $user = User::factory()->create();

        foreach (['Первая', 'Вторая'] as $title) {
            $this->actingAsUser($user)
                ->postJson(self::ENDPOINT, [
                    'type' => 'task',
                    'column' => 'backlog',
                    'title' => $title,
                    'slug' => 'SAME',
                ])
                ->assertCreated()
                ->assertJsonPath('data.slug', 'SAME');
        }

        $this->assertSame(2, Task::query()->where('slug', 'SAME')->count());
    }

    public function test_slug_can_be_changed_and_cleared(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['slug' => 'NEW'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'NEW');

        // Пустая строка от формы — «метку стёрли»: хранится null.
        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['slug' => ''])
            ->assertOk()
            ->assertJsonPath('data.slug', null);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'slug' => null]);
    }

    public function test_patch_without_slug_keeps_it(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'slug' => 'KEEP']);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['title' => 'Новое название'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'KEEP');
    }

    /**
     * slug короткий, поэтому приходит и в списке доски: форма редактирования
     * берёт начальное значение прямо из карточки доски.
     */
    public function test_index_returns_slug(): void
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'slug' => 'BOARD']);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'BOARD');
    }

    /**
     * Степень проработки задаётся при создании: идея или готова к реализации.
     * По умолчанию карточка создаётся готовой к реализации.
     */
    public function test_idea_flag_is_stored_on_create(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Задача-идея',
                'idea' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.idea', true);

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Задача на реализацию',
            ])
            ->assertCreated()
            ->assertJsonPath('data.idea', false);

        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => 'Задача-идея', 'idea' => true]);
        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => 'Задача на реализацию', 'idea' => false]);
    }

    /**
     * Степень проработки — свойство беклога: карточка, созданная сразу в работе
     * или в закрытой колонке, идеей не бывает.
     */
    public function test_idea_flag_is_ignored_outside_backlog_on_create(): void
    {
        $user = User::factory()->create();

        foreach (['in_progress', 'complete', 'canceled'] as $column) {
            $this->actingAsUser($user)
                ->postJson(self::ENDPOINT, [
                    'type' => 'task',
                    'column' => $column,
                    'title' => 'Карточка в колонке '.$column,
                    'idea' => true,
                ])
                ->assertCreated()
                ->assertJsonPath('data.idea', false);
        }
    }

    public function test_idea_flag_must_be_boolean(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Некорректная степень проработки',
                'idea' => 'idea',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idea']);
    }

    public function test_idea_flag_can_be_changed(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['idea' => true])
            ->assertOk()
            ->assertJsonPath('data.idea', true);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['idea' => false])
            ->assertOk()
            ->assertJsonPath('data.idea', false);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'idea' => false]);
    }

    /**
     * Флаг хранится у карточки, а не у колонки: переводя идею в работу,
     * значение не сбрасывается, и при возврате в беклог оно снова актуально.
     */
    public function test_idea_flag_survives_column_change(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->idea()->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['column' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.column', 'in_progress')
            ->assertJsonPath('data.idea', true);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['column' => 'backlog'])
            ->assertOk()
            ->assertJsonPath('data.column', 'backlog')
            ->assertJsonPath('data.idea', true);
    }

    /**
     * Флаг приходит и в списке доски: по нему беклог сортируется и рисует бейдж.
     */
    public function test_index_returns_idea_flag(): void
    {
        $user = User::factory()->create();
        Task::factory()->idea()->create(['user_id' => $user->id, 'column' => 'backlog']);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.idea', true);
    }

    /**
     * Порядок беклога: «на реализацию» выше, идеи ниже; внутри группы —
     * по идентификатору. Порядок одинаков на первичной загрузке и в странице.
     */
    public function test_backlog_lists_ready_cards_before_ideas(): void
    {
        $user = User::factory()->create();
        $ready = Task::factory()->create(['user_id' => $user->id, 'column' => 'backlog']);
        $idea = Task::factory()->idea()->create(['user_id' => $user->id, 'column' => 'backlog']);
        $readyLater = Task::factory()->create(['user_id' => $user->id, 'column' => 'backlog']);

        $expected = [(int) $ready->id, (int) $readyLater->id, (int) $idea->id];

        $initial = $this->actingAsUser($user)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->json('data');

        $this->assertSame($expected, array_column($initial, 'id'));

        $column = $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?column=backlog')
            ->assertOk()
            ->json('data');

        $this->assertSame($expected, array_column($column, 'id'));
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

    public function test_index_without_filters_returns_all_open_cards(): void
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
     * Первичная загрузка доски: Беклог и «В работе» приходят целиком, а в
     * «Готово» и «Отмене» — не больше 10 карточек на каждую пару «уровень +
     * колонка». Счётчики при этом полные (по фильтру), иначе клиент не знает,
     * что за пределами отданного есть ещё карточки.
     */
    public function test_initial_load_caps_closed_columns_per_level(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(25)->create(['user_id' => $user->id, 'column' => 'complete']);
        Task::factory()->count(25)->create(['user_id' => $user->id, 'column' => 'canceled']);
        Task::factory()->count(3)->create(['user_id' => $user->id, 'column' => 'backlog']);
        Task::factory()->count(4)->create(['user_id' => $user->id, 'column' => 'in_progress']);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            // 3 + 4 открытых целиком, 10 + 10 закрытых: все карточки — задачи,
            // то есть по одной паре на колонку.
            ->assertJsonCount(27, 'data')
            ->assertJsonPath('meta.total', 57)
            ->assertJsonPath('meta.columns', [
                'backlog' => 3,
                'in_progress' => 4,
                'complete' => 25,
                'canceled' => 25,
            ])
            ->assertJsonPath('meta.closed_totals.complete.task', 25)
            ->assertJsonPath('meta.closed_totals.canceled.task', 25);
    }

    /**
     * Лимит считается на пару «уровень + колонка», а не на колонку: карточки
     * каждого уровня отдают свои 10, поэтому «Готово» с тремя уровнями — это
     * 30 карточек в ответе при 75 в счётчике.
     */
    public function test_initial_load_limits_each_level_and_column_pair(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);
        $initiative = Task::factory()->initiative($direction)->inColumn(CardColumn::Complete)->create([
            'user_id' => $user->id,
        ]);
        $project = Task::factory()->project($initiative)->inColumn(CardColumn::Complete)->create([
            'user_id' => $user->id,
        ]);

        Task::factory()->count(2)->initiative($direction)->inColumn(CardColumn::Complete)->create([
            'user_id' => $user->id,
        ]);
        Task::factory()->count(2)->project($initiative)->inColumn(CardColumn::Complete)->create([
            'user_id' => $user->id,
        ]);
        // Задачи того же проекта: третий уровень в той же колонке.
        Task::factory()->count(3)->task($project)->inColumn(CardColumn::Complete)->create([
            'user_id' => $user->id,
        ]);

        /** @var list<array{column: string, type: string}> $data */
        $data = $this->actingAsUser($user)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('meta.columns.complete', 9)
            ->assertJsonPath('meta.closed_totals.complete', [
                'initiative' => 3,
                'project' => 3,
                'task' => 3,
            ])
            ->json('data');

        $byPair = array_count_values(array_map(
            static fn (array $row): string => $row['type'].'|'.$row['column'],
            $data,
        ));

        $this->assertSame([
            'initiative|complete' => 3,
            'project|complete' => 3,
            'task|complete' => 3,
        ], $byPair);
    }

    /**
     * Урезание до 10 действует на каждой паре «уровень + колонка» отдельно:
     * по 12 карточек каждого уровня в «Готово» — значит, в ответе по 10 на
     * уровень (30 всего), а не 10 на всю колонку.
     */
    public function test_initial_load_keeps_ten_cards_per_level_and_column_pair(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);
        $initiative = Task::factory()->initiative($direction)->inColumn(CardColumn::Complete)->create([
            'user_id' => $user->id,
        ]);
        $project = Task::factory()->project($initiative)->inColumn(CardColumn::Complete)->create([
            'user_id' => $user->id,
        ]);

        // Карточки создаём поштучно: у итераторной фабрики count(N) может
        // отдать N-1 запись, и тест лимита проверял бы не то, что задумано.
        for ($i = 0; $i < 11; $i++) {
            Task::factory()->initiative($direction)->inColumn(CardColumn::Complete)->create(['user_id' => $user->id]);
            Task::factory()->project($initiative)->inColumn(CardColumn::Complete)->create(['user_id' => $user->id]);
            Task::factory()->task($project)->inColumn(CardColumn::Complete)->create(['user_id' => $user->id]);
        }

        /** @var list<array{column: string, type: string}> $data */
        $data = $this->actingAsUser($user)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            // По 12 инициатив и проектов (одна инициализирующая плюс 11) и
            // 11 задач: инициализирующая карточка проекта — сам проект.
            ->assertJsonPath('meta.columns.complete', 35)
            ->assertJsonPath('meta.closed_totals.complete', [
                'initiative' => 12,
                'project' => 12,
                'task' => 11,
            ])
            ->json('data');

        $byPair = array_count_values(array_map(
            static fn (array $row): string => $row['type'].'|'.$row['column'],
            $data,
        ));

        $this->assertSame([
            'initiative|complete' => 10,
            'project|complete' => 10,
            'task|complete' => 10,
        ], $byPair);
    }

    /**
     * Фильтр по уровню не отменяет порций закрытых колонок: запрос уровня без
     * страницы — та же первичная загрузка. Открытые колонки уровня приходят
     * целиком, «Готово» и «Отмена» — по 10 (при одном уровне — ровно по одной
     * паре на колонку), карточки других уровней не попадают.
     */
    public function test_initial_load_caps_closed_columns_by_level_type(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);

        Task::factory()->count(25)->initiative($direction)->inColumn(CardColumn::Complete)->create([
            'user_id' => $user->id,
        ]);
        Task::factory()->count(25)->initiative($direction)->inColumn(CardColumn::Canceled)->create([
            'user_id' => $user->id,
        ]);
        Task::factory()->count(3)->initiative($direction)->inColumn(CardColumn::Backlog)->create([
            'user_id' => $user->id,
        ]);
        Task::factory()->count(4)->initiative($direction)->inColumn(CardColumn::InProgress)->create([
            'user_id' => $user->id,
        ]);
        // Чужой уровень: его карточки не должны ни попасть в ответ, ни исказить счётчики.
        Task::factory()->count(12)->create(['user_id' => $user->id, 'column' => 'complete']);

        $response = $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?type=initiative')
            ->assertOk()
            ->assertJsonPath('meta.total', 57)
            ->assertJsonPath('meta.columns', [
                'backlog' => 3,
                'in_progress' => 4,
                'complete' => 25,
                'canceled' => 25,
            ])
            ->assertJsonPath('meta.closed_totals.complete.initiative', 25)
            // Карточки другого уровня в счётчик пары не попадают.
            ->assertJsonPath('meta.closed_totals.complete.task', 0);

        /** @var list<array{column: string, type: string}> $data */
        $data = $response->json('data');

        $this->assertSame(['initiative'], array_values(array_unique(array_column($data, 'type'))));

        $byColumn = array_count_values(array_column($data, 'column'));

        $this->assertSame(3, $byColumn['backlog'] ?? 0);
        $this->assertSame(4, $byColumn['in_progress'] ?? 0);
        $this->assertSame(10, $byColumn['complete'] ?? 0);
        $this->assertSame(10, $byColumn['canceled'] ?? 0);
    }

    /**
     * В закрытой колонке первичной загрузки приходят самые свежие карточки:
     * старые доступны только догрузкой страницами.
     */
    public function test_initial_load_returns_newest_closed_cards(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(15)->create(['user_id' => $user->id, 'column' => 'complete']);

        /** @var list<array{id: int, column: string}> $data */
        $data = $this->actingAsUser($user)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->json('data');

        $ids = array_values(array_column(
            array_filter($data, static fn (array $row): bool => $row['column'] === 'complete'),
            'id',
        ));

        $expected = Task::query()
            ->where('user_id', $user->id)
            ->where('column', 'complete')
            ->orderByDesc('id')
            ->limit(10)
            ->pluck('id')
            ->all();

        $this->assertSame($expected, $ids);
    }

    /**
     * У каждого уровня свои 10 карточек: догрузка уровня фильтруется по типу,
     * а счётчики страницы относятся только к своему уровню.
     */
    public function test_closed_column_page_is_scoped_by_level(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);
        $initiative = Task::factory()->initiative($direction)->create(['user_id' => $user->id]);
        Task::factory()->count(14)->project($initiative)->inColumn(CardColumn::Complete)->create([
            'user_id' => $user->id,
        ]);
        Task::factory()->count(13)->create(['user_id' => $user->id, 'column' => 'complete']);

        $response = $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?type=project&column=complete&limit=10&offset=0&order=desc')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 14);

        /** @var list<array{type: string}> $data */
        $data = $response->json('data');

        $this->assertSame(['project'], array_values(array_unique(array_column($data, 'type'))));
    }

    /**
     * Догрузка следующих карточек закрытой колонки продолжает выборку
     * без пересечений с первой порцией первичной загрузки.
     */
    public function test_next_page_of_closed_column_continues_after_initial_load(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(25)->create(['user_id' => $user->id, 'column' => 'canceled']);

        /** @var list<array{id: int}> $first */
        $first = $this->actingAsUser($user)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->json('data');

        $second = $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?column=canceled&limit=10&offset=10&order=desc')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 25);

        /** @var list<int> $firstIds */
        $firstIds = array_column($first, 'id');
        /** @var list<int> $secondIds */
        $secondIds = array_column($second->json('data'), 'id');

        $this->assertSame([], array_values(array_intersect($firstIds, $secondIds)));
    }

    /**
     * Урезание действует и внутри свимлайна: доска грузит ветку направления
     * тем же запросом без колонки, и закрытые колонки ветки тоже по 10.
     */
    public function test_initial_load_applies_to_parent_filter(): void
    {
        $user = User::factory()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);
        $initiative = Task::factory()->initiative($direction)->create(['user_id' => $user->id]);
        Task::factory()->count(15)->project($initiative)->inColumn(CardColumn::Complete)->create([
            'user_id' => $user->id,
        ]);
        Task::factory()->count(12)->create(['user_id' => $user->id, 'column' => 'complete']);

        $response = $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?initiative_id='.$initiative->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 15);

        /** @var list<array{column: string}> $data */
        $data = $response->json('data');

        $byColumn = array_count_values(array_column($data, 'column'));

        $this->assertSame(10, $byColumn['complete'] ?? 0);
    }

    /**
     * Явный фильтр по колонке без страницы — это запрос «вся колонка»:
     * первичное урезание к нему не применяется, поведение прежнее.
     */
    public function test_explicit_column_without_limit_returns_whole_column(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(15)->create(['user_id' => $user->id, 'column' => 'complete']);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?column=complete')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 15);
    }

    /**
     * Первая страница закрытой колонки: свежие карточки сверху, а общее число
     * приходит в meta — по нему клиент рисует кнопку «показать ещё».
     */
    public function test_index_page_returns_newest_cards_with_total(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(15)->create(['user_id' => $user->id, 'column' => 'complete']);

        $response = $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?column=complete&limit=10&offset=0&order=desc')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 15);

        /** @var list<int> $ids */
        $ids = array_column($response->json('data'), 'id');

        // Порядок — по убыванию идентификатора: самые новые карточки сверху.
        $sorted = $ids;
        rsort($sorted);

        $this->assertSame($sorted, $ids);
    }

    public function test_index_last_page_has_no_more_cards(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(15)->create(['user_id' => $user->id, 'column' => 'complete']);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?column=complete&limit=10&offset=10&order=desc')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 15);
    }

    /**
     * Метаданные одинаковы в обоих режимах: счётчики по колонкам есть и у
     * страницы, поэтому клиенту не нужно помнить, какой режим он вызвал.
     * Счётчики считаются по тем же фильтрам: фильтр по колонке обнуляет
     * остальные — они не входят в выборку.
     */
    public function test_page_also_returns_column_totals(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(15)->create(['user_id' => $user->id, 'column' => 'complete']);
        Task::factory()->count(2)->create(['user_id' => $user->id, 'column' => 'canceled']);

        $response = $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?column=complete&limit=10&offset=0&order=desc')
            ->assertOk()
            ->assertJsonPath('meta.columns', [
                'backlog' => 0,
                'in_progress' => 0,
                'complete' => 15,
                'canceled' => 0,
            ]);

        // Форма meta зафиксирована: total, columns и closed_totals. «Есть ещё»
        // клиент выводит сам, сравнивая счётчик пары с уже загруженным количеством.
        $this->assertSame(['total', 'columns', 'closed_totals'], array_keys($response->json('meta')));
        $this->assertSame([
            'complete' => ['initiative' => 0, 'project' => 0, 'task' => 15],
            'canceled' => ['initiative' => 0, 'project' => 0, 'task' => 0],
        ], $response->json('meta.closed_totals'));
    }

    /**
     * Ограничение страницы действует только на свою выборку: карточки других
     * колонок под тот же фильтр не попадают.
     */
    public function test_index_page_does_not_mix_columns(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(3)->create(['user_id' => $user->id, 'column' => 'complete']);
        Task::factory()->count(2)->create(['user_id' => $user->id, 'column' => 'canceled']);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?column=canceled&limit=10&offset=0&order=desc')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    /**
     * Страница без order сохраняет прежний порядок доски (по возрастанию id),
     * чтобы «показать ещё» не перемешивал карточки между страницами.
     */
    public function test_index_page_keeps_ascending_order_by_default(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(3)->create(['user_id' => $user->id, 'column' => 'canceled']);

        $response = $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?column=canceled&limit=2')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        /** @var list<int> $ids */
        $ids = array_column($response->json('data'), 'id');

        $sorted = $ids;
        sort($sorted);

        $this->assertSame($sorted, $ids);
    }

    /**
     * Опечатка в параметрах страницы — ошибка клиента: тихо отданный «весь
     * список» скрыл бы баг пагинации.
     */
    public function test_index_rejects_invalid_page_parameters(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?column=complete&limit=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?column=complete&limit=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?offset=10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['offset']);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?column=complete&limit=10&order=random')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order']);
    }

    /**
     * Страница без колонки отклоняется: лимит закрытых карточек действует на
     * пару «уровень + колонка», и догрузка всегда адресная. Кусок смешанного
     * списка сломал бы счётчики пары у клиента.
     */
    public function test_index_rejects_page_without_column(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?limit=10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT.'?type=initiative&limit=10&offset=10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);
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

    /**
     * Приоритет задаётся при создании; без поля карточка становится средней.
     */
    public function test_priority_is_stored_on_create(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Срочная задача',
                'priority' => 'high',
            ])
            ->assertCreated()
            ->assertJsonPath('data.priority', 'high');

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Задача без выбранного приоритета',
            ])
            ->assertCreated()
            ->assertJsonPath('data.priority', 'medium');

        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => 'Срочная задача', 'priority' => 'high']);
        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Задача без выбранного приоритета',
            'priority' => 'medium',
        ]);
    }

    /**
     * Приоритет не привязан к колонке: карточка, созданная сразу в работе,
     * сохраняет выбранный приоритет — в отличие от флага «идея».
     */
    public function test_priority_is_kept_outside_backlog_on_create(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'in_progress',
                'title' => 'Задача сразу в работе',
                'priority' => 'low',
            ])
            ->assertCreated()
            ->assertJsonPath('data.priority', 'low');
    }

    public function test_priority_must_be_known_value(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->postJson(self::ENDPOINT, [
                'type' => 'task',
                'column' => 'backlog',
                'title' => 'Неизвестный приоритет',
                'priority' => 'urgent',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['priority']);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['priority' => 'urgent'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_priority_can_be_changed(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['priority' => 'high'])
            ->assertOk()
            ->assertJsonPath('data.priority', 'high');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'priority' => 'high']);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['priority' => 'low'])
            ->assertOk()
            ->assertJsonPath('data.priority', 'low');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'priority' => 'low']);
    }

    /**
     * Приоритет хранится у карточки, а не у колонки: перевод в закрытую колонку
     * значение не сбрасывает (клиент просто не показывает бейдж).
     */
    public function test_priority_survives_column_change(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->priority(CardPriority::High)->create(['user_id' => $user->id]);

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['column' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.column', 'in_progress')
            ->assertJsonPath('data.priority', 'high');

        $this->actingAsUser($user)
            ->patchJson(self::ENDPOINT.'/'.$task->id, ['column' => 'complete'])
            ->assertOk()
            ->assertJsonPath('data.column', 'complete')
            ->assertJsonPath('data.priority', 'high');
    }

    /**
     * Приоритет приходит и в списке доски: плитка выводит его бейджем.
     */
    public function test_index_returns_priority(): void
    {
        $user = User::factory()->create();
        Task::factory()->priority(CardPriority::Low)->create(['user_id' => $user->id, 'column' => 'backlog']);

        $this->actingAsUser($user)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.priority', 'low');
    }
}
