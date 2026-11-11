<?php

namespace Tests\Feature\Api;

use App\Modules\Direction\Domain\Direction;
use App\Modules\Task\Application\Commands\CreateTask\CreateTaskCommand;
use App\Modules\Task\Application\Commands\CreateTaskForAdmin\CreateTaskForAdminCommand;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;
use App\Modules\User\Domain\Exceptions\ReadOnlyMode;
use App\Modules\User\Domain\User;
use App\Shared\Application\Bus\CommandBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Режим «только чтение»: флаг users.readonly.
 *
 * Пользователь с флагом видит данные, но не меняет их. Правило проверяется на
 * трёх уровнях:
 *  - HTTP: любой изменяющий запрос получает 403 (DenyReadOnlyWrites);
 *  - выход из аккаунта разрешён — пользователь всегда может закрыть сессию;
 *  - шина команд: прямой dispatch вне HTTP тоже упирается в запрет
 *    (DenyReadOnlyCommands), а команды админки — нет: панель модерирует данные
 *    пользователя, и флаг его учётной записи здесь не ограничение.
 *
 * Отдельно фиксируется контракт: флаг приходит в /auth/me (по нему клиент
 * показывает плашку) и не поднимается через публичную регистрацию.
 */
final class ReadOnlyModeTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(User $user): self
    {
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token);

        return $this;
    }

    /**
     * Доска read-only пользователя: направление и инициатива в беклоге.
     *
     * @return array{direction: Direction, task: Task}
     */
    private function boardOf(User $user): array
    {
        $direction = Direction::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->initiative($direction)->create(['user_id' => $user->id]);

        return ['direction' => $direction, 'task' => $task];
    }

    public function test_readonly_user_cannot_create_direction(): void
    {
        $user = User::factory()->readonly()->create();

        $this->actingAsUser($user)
            ->postJson('/api/v1/directions', ['title' => 'Здоровье', 'color' => 'c3'])
            ->assertForbidden()
            ->assertJsonPath('errors.account.0', ReadOnlyMode::denied()->getMessage());

        $this->assertDatabaseMissing('directions', ['user_id' => $user->id]);
    }

    public function test_readonly_user_cannot_change_or_delete_direction(): void
    {
        $user = User::factory()->readonly()->create();
        $board = $this->boardOf($user);

        $this->actingAsUser($user)
            ->patchJson('/api/v1/directions/'.$board['direction']->id, ['title' => 'Новое'])
            ->assertForbidden();

        $this->actingAsUser($user)
            ->deleteJson('/api/v1/directions/'.$board['direction']->id)
            ->assertForbidden();

        $this->assertDatabaseHas('directions', [
            'id' => $board['direction']->id,
            'title' => $board['direction']->title,
        ]);
    }

    public function test_readonly_user_cannot_create_change_or_delete_task(): void
    {
        $user = User::factory()->readonly()->create();
        $board = $this->boardOf($user);

        $this->actingAsUser($user)
            ->postJson('/api/v1/tasks', [
                'type' => 'project',
                'column' => 'backlog',
                'title' => 'Новый проект',
            ])
            ->assertForbidden();

        $this->actingAsUser($user)
            ->patchJson('/api/v1/tasks/'.$board['task']->id, ['column' => 'in_progress'])
            ->assertForbidden();

        $this->actingAsUser($user)
            ->deleteJson('/api/v1/tasks/'.$board['task']->id)
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $board['task']->id,
            'column' => CardColumn::Backlog->value,
        ]);
    }

    public function test_readonly_user_cannot_change_settings(): void
    {
        $user = User::factory()->readonly()->create();

        $this->actingAsUser($user)
            ->putJson('/api/v1/user/settings', [
                'settings' => ['board.collapsed_levels' => '{"strategic":true}'],
            ])
            ->assertForbidden();

        $this->actingAsUser($user)
            ->deleteJson('/api/v1/user/settings/board.collapsed_levels')
            ->assertForbidden();
    }

    public function test_readonly_user_keeps_read_access(): void
    {
        $user = User::factory()->readonly()->create();
        $board = $this->boardOf($user);

        $this->actingAsUser($user)
            ->getJson('/api/v1/directions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $board['direction']->id);

        $this->actingAsUser($user)
            ->getJson('/api/v1/tasks')
            ->assertOk();

        $this->actingAsUser($user)
            ->getJson('/api/v1/tasks/'.$board['task']->id)
            ->assertOk()
            ->assertJsonPath('data.id', $board['task']->id);

        $this->actingAsUser($user)
            ->getJson('/api/v1/user/settings')
            ->assertOk();
    }

    public function test_readonly_user_can_log_out(): void
    {
        $user = User::factory()->readonly()->create();
        $token = $user->createToken('phpunit');

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        // Токен отозван: повторный запрос с ним — уже гость.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_readonly_flag_is_exposed_and_not_settable_through_registration(): void
    {
        $user = User::factory()->readonly()->create();

        $this->actingAsUser($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.readonly', true);
    }

    public function test_readonly_flag_is_not_settable_through_registration(): void
    {
        $this->postJson('/api/v1/user/register', [
            'email' => 'intruder@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_name' => 'phpunit',
            // Поле не проходит валидацию FormRequest и в команду не попадает.
            'readonly' => true,
        ])->assertCreated();

        $registered = User::query()->where('email', 'intruder@example.test')->firstOrFail();

        $this->assertFalse($registered->isReadonly());
    }

    public function test_command_bus_denies_writes_inside_http_request(): void
    {
        $user = User::factory()->readonly()->create();

        // Точка входа — обычный запрос: внутри него Auth::user() известен
        // (гвард sanctum), и пайп шины видит read-only пользователя.
        Route::middleware('auth:sanctum')->post('/__test/readonly-dispatch', function (): JsonResponse {
            app(CommandBus::class)->dispatch(new CreateTaskCommand(
                CardType::Project,
                CardColumn::Backlog,
                TaskTitle::fromString('Проект без инициативы'),
            ));

            return response()->json(['dispatched' => true]);
        });

        $this->actingAsUser($user)
            ->postJson('/__test/readonly-dispatch')
            ->assertForbidden()
            ->assertJsonPath('errors.account.0', ReadOnlyMode::denied()->getMessage());

        // Транзакция откатилась: карточка не создана.
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_admin_commands_are_not_blocked_by_readonly_flag(): void
    {
        $admin = User::factory()->admin()->create();
        $readonlyOwner = User::factory()->readonly()->create();
        $direction = Direction::factory()->create(['user_id' => $readonlyOwner->id]);

        Route::middleware('auth:sanctum')->post('/__test/admin-dispatch', function (Request $request): JsonResponse {
            app(CommandBus::class)->dispatch(new CreateTaskForAdminCommand(
                (int) $request->input('owner_id'),
                new CreateTaskCommand(
                    CardType::Initiative,
                    CardColumn::Backlog,
                    TaskTitle::fromString('Инициатива от администратора'),
                    directionId: (int) $request->input('direction_id'),
                ),
            ));

            return response()->json(['dispatched' => true]);
        });

        $this->actingAsUser($admin)
            ->postJson('/__test/admin-dispatch', [
                'owner_id' => $readonlyOwner->id,
                'direction_id' => $direction->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('tasks', [
            'user_id' => $readonlyOwner->id,
            'title' => 'Инициатива от администратора',
        ]);
    }

    public function test_regular_user_is_not_affected(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson('/api/v1/directions', ['title' => 'Здоровье', 'color' => 'c3'])
            ->assertCreated();

        $this->assertDatabaseHas('directions', ['user_id' => $user->id, 'title' => 'Здоровье']);
    }
}
