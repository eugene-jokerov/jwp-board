<?php

namespace Tests\Feature\Shared\Bus;

use App\Modules\Direction\Application\Commands\CreateDirection\CreateDirectionCommand;
use App\Modules\Direction\Application\Commands\CreateDirectionForAdmin\CreateDirectionForAdminCommand;
use App\Modules\Direction\Application\Commands\DeleteDirection\DeleteDirectionCommand;
use App\Modules\Direction\Application\Commands\DeleteDirectionForAdmin\DeleteDirectionForAdminCommand;
use App\Modules\Direction\Application\Commands\UpdateDirection\UpdateDirectionCommand;
use App\Modules\Direction\Application\Commands\UpdateDirectionForAdmin\UpdateDirectionForAdminCommand;
use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;
use App\Modules\Task\Application\Commands\CreateTask\CreateTaskCommand;
use App\Modules\Task\Application\Commands\CreateTaskForAdmin\CreateTaskForAdminCommand;
use App\Modules\Task\Application\Commands\DeleteTask\DeleteTaskCommand;
use App\Modules\Task\Application\Commands\DeleteTaskForAdmin\DeleteTaskForAdminCommand;
use App\Modules\Task\Application\Commands\UpdateTask\UpdateTaskCommand;
use App\Modules\Task\Application\Commands\UpdateTaskForAdmin\UpdateTaskForAdminCommand;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;
use App\Modules\User\Application\Commands\DeleteUserSettings\DeleteUserSettingsCommand;
use App\Modules\User\Application\Commands\RegisterUser\RegisterUserCommand;
use App\Modules\User\Application\Commands\UpdateUserSettings\UpdateUserSettingsCommand;
use App\Modules\User\Domain\Exceptions\ReadOnlyMode;
use App\Modules\User\Domain\User;
use App\Modules\User\Domain\ValueObjects\MetaKey;
use App\Shared\Application\Bus\CommandBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Пайп шины команд `DenyReadOnlyCommands` вне HTTP-контекста.
 *
 * HTTP-запросы отсекает DenyReadOnlyWrites, поэтому здесь проверяется вторая
 * точка входа: прямой dispatch (tinker, консольная команда, планировщик).
 * Пайп опирается на Auth::user(), поэтому сценарий начинается с Auth::login().
 *
 * Отдельно фиксируется карта пайпов из BusServiceProvider: команды админки и
 * регистрация идут без проверки права на запись — администратор модерирует
 * доски read-only пользователей из /admin.
 */
final class ReadOnlyCommandPipesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Шина команд из контейнера: те же привязки и пайпы, что в приложении.
     */
    private function bus(): CommandBus
    {
        return $this->app->make(CommandBus::class);
    }

    /**
     * Направление и инициатива read-only пользователя — объекты, над которыми
     * работают команды из набора ниже.
     *
     * @return array{user: User, direction: Direction, task: Task}
     */
    private function boardOfReadonlyUser(): array
    {
        $user = User::factory()->readonly()->create();
        $direction = Direction::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->initiative($direction)->create(['user_id' => $user->id]);

        return ['user' => $user, 'direction' => $direction, 'task' => $task];
    }

    /**
     * Команды прикладного уровня: каждая меняет данные пользователя.
     *
     * Набор соответствует WRITE_PIPES из BusServiceProvider: новая команда без
     * теста здесь сразу заметна по расхождению с картой.
     *
     * @param  array{user: User, direction: Direction, task: Task}  $board
     * @return array<string, object>
     */
    private function writeCommands(array $board): array
    {
        $userId = (int) $board['user']->id;
        $directionId = (int) $board['direction']->id;
        $taskId = (int) $board['task']->id;

        return [
            'CreateDirectionCommand' => new CreateDirectionCommand(
                $userId,
                DirectionTitle::fromString('Новое направление'),
                DirectionColor::Blue,
            ),
            'UpdateDirectionCommand' => new UpdateDirectionCommand(
                $userId,
                $directionId,
                DirectionTitle::fromString('Переименованное'),
            ),
            'DeleteDirectionCommand' => new DeleteDirectionCommand($userId, $directionId),
            'CreateTaskCommand' => new CreateTaskCommand(
                CardType::Project,
                CardColumn::Backlog,
                TaskTitle::fromString('Новый проект'),
                userId: $userId,
            ),
            'UpdateTaskCommand' => new UpdateTaskCommand(
                $userId,
                $taskId,
                changes: ['column' => CardColumn::InProgress],
            ),
            'DeleteTaskCommand' => new DeleteTaskCommand($userId, $taskId),
            'UpdateUserSettingsCommand' => new UpdateUserSettingsCommand(
                $userId,
                ['board.theme' => '"dark"'],
            ),
            'DeleteUserSettingsCommand' => new DeleteUserSettingsCommand(
                $userId,
                MetaKey::fromString('board.theme'),
            ),
        ];
    }

    /**
     * Названия команд прикладного уровня — по одному тесту на каждую.
     *
     * @return array<string, array{0: string}>
     */
    public static function writeCommandProvider(): array
    {
        $names = [
            'CreateDirectionCommand',
            'UpdateDirectionCommand',
            'DeleteDirectionCommand',
            'CreateTaskCommand',
            'UpdateTaskCommand',
            'DeleteTaskCommand',
            'UpdateUserSettingsCommand',
            'DeleteUserSettingsCommand',
        ];

        $cases = [];

        foreach ($names as $name) {
            $cases[$name] = [$name];
        }

        return $cases;
    }

    /**
     * Каждая команда прикладного уровня запрещена read-only пользователю,
     * и данные после отказа не тронуты (транзакция откатилась).
     */
    #[DataProvider('writeCommandProvider')]
    public function test_readonly_user_cannot_dispatch_write_command(string $commandName): void
    {
        $board = $this->boardOfReadonlyUser();
        $command = $this->writeCommands($board)[$commandName];

        Auth::login($board['user']);

        try {
            $this->bus()->dispatch($command);
            self::fail(sprintf('Команда %s должна была упасть с ReadOnlyMode', $commandName));
        } catch (ReadOnlyMode $e) {
            self::assertSame(ReadOnlyMode::denied()->getMessage(), $e->getMessage());
        }

        $this->assertDatabaseHas('directions', [
            'id' => $board['direction']->id,
            'title' => $board['direction']->title,
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $board['task']->id,
            'column' => CardColumn::Backlog->value,
        ]);
        $this->assertDatabaseCount('directions', 1);
        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseCount('user_meta', 0);
    }

    /**
     * Гость (контекст без аутентификации): пайп молчит и ограничение отдаёт
     * сам хендлер. Именно так работают tinker и консольные команды, где
     * пользователь в Auth не залогинен.
     */
    public function test_guest_context_is_not_blocked_by_the_pipe(): void
    {
        Auth::forgetGuards();

        $user = User::factory()->readonly()->create();

        // Владелец передан в команде явно — проверка режима в таком контексте
        // невозможна, поэтому ответственность за неё лежит на вызывающем.
        $this->bus()->dispatch(new CreateDirectionCommand(
            (int) $user->id,
            DirectionTitle::fromString('Из консоли'),
            DirectionColor::Blue,
        ));

        $this->assertDatabaseHas('directions', [
            'user_id' => $user->id,
            'title' => 'Из консоли',
        ]);
    }

    /**
     * Регистрация идёт без проверки права на запись: новой учётной записи
     * ещё нет, и флаг предыдущей сессии не должен блокировать регистрацию.
     */
    public function test_registration_command_is_not_blocked(): void
    {
        Auth::login(User::factory()->readonly()->create());

        $this->bus()->dispatch(RegisterUserCommand::fromArray([
            'email' => 'newcomer@example.test',
            'password' => 'password123',
        ]));

        $registered = User::query()->where('email', 'newcomer@example.test')->firstOrFail();

        self::assertFalse($registered->isReadonly());
    }

    /**
     * Команды админки не получают пайп запрета: администратор правит доски
     * read-only пользователей из /admin (даже если его учётка сама read-only).
     */
    public function test_admin_task_commands_are_not_blocked_for_readonly_actor(): void
    {
        $board = $this->boardOfReadonlyUser();
        $ownerId = (int) $board['user']->id;

        Auth::login(User::factory()->admin()->readonly()->create());

        // Создание карточки в доске read-only владельца.
        $this->bus()->dispatch(new CreateTaskForAdminCommand(
            $ownerId,
            new CreateTaskCommand(
                CardType::Project,
                CardColumn::Backlog,
                TaskTitle::fromString('Проект от администратора'),
                initiativeId: (int) $board['task']->id,
            ),
        ));

        $this->assertDatabaseHas('tasks', [
            'user_id' => $ownerId,
            'title' => 'Проект от администратора',
        ]);

        // Изменение карточки владельца.
        $this->bus()->dispatch(new UpdateTaskForAdminCommand(
            $ownerId,
            (int) $board['task']->id,
            changes: ['title' => TaskTitle::fromString('Переименована админом')],
        ));

        $this->assertDatabaseHas('tasks', [
            'id' => $board['task']->id,
            'title' => 'Переименована админом',
        ]);
    }

    /**
     * Удаление карточки владельца из панели: каскад (режим модерации)
     * проходит без проверки права на запись.
     */
    public function test_admin_delete_task_command_is_not_blocked(): void
    {
        $board = $this->boardOfReadonlyUser();
        $ownerId = (int) $board['user']->id;

        $project = Task::factory()->project($board['task'])->create(['user_id' => $ownerId]);

        Auth::login(User::factory()->admin()->create());

        $this->bus()->dispatch(new DeleteTaskForAdminCommand($ownerId, (int) $board['task']->id, cascade: true));

        $this->assertDatabaseMissing('tasks', ['id' => $board['task']->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $project->id]);
    }

    /**
     * Направления владельца из панели: создание, переименование и удаление —
     * без проверки режима учётной записи владельца.
     */
    public function test_admin_direction_commands_are_not_blocked(): void
    {
        $board = $this->boardOfReadonlyUser();
        $ownerId = (int) $board['user']->id;

        Auth::login(User::factory()->admin()->create());

        $this->bus()->dispatch(new CreateDirectionForAdminCommand(
            $ownerId,
            DirectionTitle::fromString('Направление админа'),
            DirectionColor::Amber,
        ));

        $created = Direction::query()
            ->where('user_id', $ownerId)
            ->where('title', 'Направление админа')
            ->firstOrFail();

        $this->bus()->dispatch(new UpdateDirectionForAdminCommand(
            $ownerId,
            (int) $created->id,
            DirectionTitle::fromString('Переименовано админом'),
        ));

        $this->assertDatabaseHas('directions', [
            'id' => $created->id,
            'title' => 'Переименовано админом',
        ]);

        $this->bus()->dispatch(new DeleteDirectionForAdminCommand($ownerId, (int) $created->id));

        $this->assertDatabaseMissing('directions', ['id' => $created->id]);
    }

    /**
     * Команда, которой нет в карте пайпов, получает WRITE_PIPES: безопасное
     * поведение по умолчанию — проверить право на запись всё равно.
     */
    public function test_command_outside_the_map_gets_write_pipes(): void
    {
        Auth::login(User::factory()->readonly()->create());

        $this->expectException(ReadOnlyMode::class);

        $this->bus()->dispatch(new ReadOnlyPipeTestCommand);
    }

    /**
     * Запрет не «залипает» между вызовами: после выхода из аккаунта пайп
     * больше не блокирует (важно для tinker и последовательных сценариев).
     */
    public function test_guard_is_reset_after_logout(): void
    {
        $board = $this->boardOfReadonlyUser();
        $userId = (int) $board['user']->id;

        Auth::login($board['user']);

        try {
            $this->bus()->dispatch(new CreateDirectionCommand(
                $userId,
                DirectionTitle::fromString('Запрещено'),
                DirectionColor::Blue,
            ));

            self::fail('Пока пользователь в Auth, команда должна быть запрещена');
        } catch (ReadOnlyMode) {
            // Ожидаемо: запрет сработал.
        }

        $this->assertDatabaseMissing('directions', ['title' => 'Запрещено']);

        // Сессия программы (не HTTP): выходим — пайп молчит.
        Auth::logout();
        Auth::forgetGuards();

        $this->bus()->dispatch(new CreateDirectionCommand(
            $userId,
            DirectionTitle::fromString('Разрешено после выхода'),
            DirectionColor::Blue,
        ));

        $this->assertDatabaseHas('directions', [
            'user_id' => $userId,
            'title' => 'Разрешено после выхода',
        ]);
    }
}

/**
 * Команда, отсутствующая в карте COMMAND_PIPES: нужна, чтобы проверить
 * поведение шины по умолчанию (WRITE_PIPES).
 */
final readonly class ReadOnlyPipeTestCommand {}
