<?php

namespace Tests\Feature\Api;

use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Infrastructure\Persistence\EloquentDirectionModel;
use App\Modules\Task\Application\Commands\CreateTask\CreateTaskCommand;
use App\Modules\Task\Application\Commands\CreateTaskForAdmin\CreateTaskForAdminCommand;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;
use App\Modules\Task\Infrastructure\Persistence\EloquentTaskModel;
use App\Modules\User\Domain\Exceptions\ReadOnlyMode;
use App\Modules\User\Domain\User;
use App\Modules\User\Infrastructure\Persistence\EloquentUserModel;
use App\Shared\Application\Bus\CommandBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
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
 * Отдельно фиксируется контракт: флаг приходит в ответах с пользователем
 * (по нему клиент показывает плашку) и не поднимается ни регистрацией,
 * ни через массовое заполнение модели.
 *
 * Тесты намеренно идут блоками — по одному хуку, который несёт режим:
 *  - запрет мутаций по HTTP (перебор методов и эндпоинтов);
 *  - порядок проверок (403 раньше валидации, 404, 409 и 405);
 *  - исключения: выход из аккаунта и методы чтения;
 *  - гости и обычные пользователи не затронуты;
 *  - контракт ответа 403 и поля readonly;
 *  - веб-канал (сессия + CSRF) ведёт себя так же, как токенный;
 *  - смена флага в рантайме и неприкосновенность не-API маршрутов.
 */
final class ReadOnlyModeTest extends TestCase
{
    use RefreshDatabase;

    /** Origin веб-клиента: по нему Sanctum включает сессию и CSRF. */
    private const string ORIGIN = 'http://localhost:8081';

    /** Имя cookie сессии Laravel: Str::snake(APP_NAME) + '_session'. */
    private const string SESSION_COOKIE = 'j_w_p_board_session';

    /**
     * Подставить Bearer-токен пользователя в заголовки следующих запросов.
     */
    private function actingAsUser(User $user): self
    {
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token);

        return $this;
    }

    /**
     * Войти веб-клиентом и вернуть сессионную cookie.
     *
     * @return string значение cookie j_w_p_board_session
     */
    private function webSessionCookie(User $user, string $password = 'secret-password'): string
    {
        $response = $this->withHeader('Origin', self::ORIGIN)
            ->postJson('/api/v1/auth/web/login', [
                'email' => $user->email,
                'password' => $password,
            ])
            ->assertOk();

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie): bool => $cookie->getName() === self::SESSION_COOKIE);

        self::assertNotNull($cookie, 'Сессионная cookie не выставлена');

        return (string) $cookie->getValue();
    }

    /**
     * Ожидаемое тело отказа: режим — это право на запись, а не ошибка поля.
     *
     * @return array{message: string, errors: array{account: array<int, string>}}
     */
    private function expectedDenial(): array
    {
        $message = ReadOnlyMode::denied()->getMessage();

        return [
            'message' => $message,
            'errors' => [
                'account' => [$message],
            ],
        ];
    }

    /**
     * Доска read-only пользователя: направление и инициатива в беклоге.
     *
     * @return array{direction: Direction, task: Task}
     */
    private function boardOf(User $user): array
    {
        $direction = EloquentDirectionModel::factory()->create(['user_id' => $user->id]);
        $task = EloquentTaskModel::factory()->initiative($direction)->create(['user_id' => $user->id]);

        return ['direction' => $direction, 'task' => $task];
    }

    /**
     * Инициатива с проектом и задачей: «непустые» родители для проверки 409.
     *
     * @return array{initiative: Task, project: Task, task: Task}
     */
    private function hierarchyOf(User $user): array
    {
        $direction = EloquentDirectionModel::factory()->create(['user_id' => $user->id]);

        $initiative = EloquentTaskModel::factory()->initiative($direction)->create(['user_id' => $user->id]);
        $project = EloquentTaskModel::factory()->project($initiative)->create(['user_id' => $user->id]);
        $task = EloquentTaskModel::factory()->task($project)->create(['user_id' => $user->id]);

        return ['initiative' => $initiative, 'project' => $project, 'task' => $task];
    }

    /**
     * Изменяющие запросы к API в «токенном» канале.
     *
     * Один список на все проверки запрета: новый изменяющий эндпоинт достаточно
     * вписать сюда, и он автоматически проверится и на запрет, и на регресс
     * для обычного пользователя. Ответы здесь не сверяются — важен статус,
     * поэтому невалидные тела допустимы: до валидации дело не доходит.
     *
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>, 3: int}>
     */
    private function writeRequests(int $directionId, int $taskId): array
    {
        return [
            'POST directions' => ['POST', '/api/v1/directions', ['title' => 'Здоровье', 'color' => 'c3'], Response::HTTP_CREATED],
            'PATCH direction' => ['PATCH', '/api/v1/directions/'.$directionId, ['title' => 'Новое направление'], Response::HTTP_OK],
            'DELETE direction' => ['DELETE', '/api/v1/directions/'.$directionId, [], Response::HTTP_NO_CONTENT],
            'POST tasks' => ['POST', '/api/v1/tasks', ['type' => 'project', 'column' => 'backlog', 'title' => 'Новый проект'], Response::HTTP_CREATED],
            'PATCH task' => ['PATCH', '/api/v1/tasks/'.$taskId, ['column' => CardColumn::InProgress->value], Response::HTTP_OK],
            'DELETE task' => ['DELETE', '/api/v1/tasks/'.$taskId, [], Response::HTTP_NO_CONTENT],
            'PUT settings' => ['PUT', '/api/v1/user/settings', ['settings' => ['board.collapsed_levels' => '{"strategic":true}']], Response::HTTP_OK],
            'DELETE setting' => ['DELETE', '/api/v1/user/settings/board.collapsed_levels', [], Response::HTTP_NO_CONTENT],
        ];
    }

    /**
     * Отправить запрос с собственным токеном пользователя.
     *
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    private function jsonAsUser(User $user, string $method, string $uri, array $payload = []): TestResponse
    {
        $token = $user->createToken('phpunit')->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer '.$token)->json($method, $uri, $payload);
    }

    // ------------------------------------------------------------------
    // Запрет мутаций по HTTP
    // ------------------------------------------------------------------

    public function test_readonly_user_cannot_create_direction(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create();

        $this->actingAsUser($user)
            ->postJson('/api/v1/directions', ['title' => 'Здоровье', 'color' => 'c3'])
            ->assertForbidden()
            ->assertJsonPath('errors.account.0', ReadOnlyMode::denied()->getMessage());

        $this->assertDatabaseMissing('directions', ['user_id' => $user->id]);
    }

    public function test_readonly_user_cannot_change_or_delete_direction(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create();
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
        $user = EloquentUserModel::factory()->readonly()->create();
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
        $user = EloquentUserModel::factory()->readonly()->create();

        $this->actingAsUser($user)
            ->putJson('/api/v1/user/settings', [
                'settings' => ['board.collapsed_levels' => '{"strategic":true}'],
            ])
            ->assertForbidden();

        $this->actingAsUser($user)
            ->deleteJson('/api/v1/user/settings/board.collapsed_levels')
            ->assertForbidden();

        // Настройка не создана: отказ произошёл до хендлера.
        $this->assertDatabaseMissing('user_meta', ['user_id' => $user->id]);
    }

    /**
     * Все изменяющие маршруты API сразу: 403 и ни одной записи в БД.
     *
     * Список не привязан к конкретному контроллеру — проверка идёт до него,
     * поэтому каждый эндпоинт отвечает отказом ещё до валидации.
     */
    public function test_every_write_endpoint_is_forbidden_for_readonly_user(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create();
        $board = $this->boardOf($user);

        $directionState = ['id' => $board['direction']->id, 'title' => $board['direction']->title];
        $taskState = ['id' => $board['task']->id, 'column' => CardColumn::Backlog->value];

        foreach ($this->writeRequests($board['direction']->id, $board['task']->id) as $name => [$method, $uri, $payload]) {
            $this->jsonAsUser($user, $method, $uri, $payload)
                ->assertForbidden();

            // Запрос не дошёл до контроллера — состояние не изменилось.
            $this->assertDatabaseHas('directions', $directionState);
            $this->assertDatabaseHas('tasks', $taskState);
            $this->assertDatabaseCount('directions', 1);
            $this->assertDatabaseCount('tasks', 1);
            $this->assertDatabaseCount('user_meta', 0);
        }
    }

    /**
     * Чтение перед записью в одном тесте: гвард `sanctum` кеширует
     * пользователя после первого запроса, и запрет обязан пережить
     * последовательность «read → write» в одном процессе.
     */
    public function test_read_before_write_does_not_weaken_the_guard(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create();
        $board = $this->boardOf($user);

        $this->actingAsUser($user);

        $this->getJson('/api/v1/tasks')->assertOk();
        $this->getJson('/api/v1/directions')->assertOk();
        $this->getJson('/api/v1/user/settings')->assertOk();
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.readonly', true);

        $this->postJson('/api/v1/tasks', [
            'type' => 'project',
            'column' => 'backlog',
            'title' => 'После чтения',
        ])->assertForbidden();

        $this->patchJson('/api/v1/tasks/'.$board['task']->id, ['column' => 'in_progress'])
            ->assertForbidden();

        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseHas('tasks', [
            'id' => $board['task']->id,
            'column' => CardColumn::Backlog->value,
        ]);
    }

    // ------------------------------------------------------------------
    // Порядок проверок: 403 раньше валидации, 404, 409 и 405
    // ------------------------------------------------------------------

    /**
     * Режим — это право, а не валидация: невалидное тело даёт 403, а не 422.
     * Иначе клиент решил бы, что запрос «почти» прошёл и дело в данных.
     */
    public function test_forbidden_wins_over_validation_error(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create();
        $board = $this->boardOf($user);

        // Обязательные поля отсутствуют — без режима был бы 422.
        $this->actingAsUser($user)
            ->postJson('/api/v1/directions', [])
            ->assertForbidden();

        // Цвет вне палитры — без режима тоже 422.
        $this->actingAsUser($user)
            ->patchJson('/api/v1/directions/'.$board['direction']->id, ['color' => 'c0'])
            ->assertForbidden();

        // Настройки без ключа settings — без режима 422.
        $this->actingAsUser($user)
            ->putJson('/api/v1/user/settings', [])
            ->assertForbidden();
    }

    /**
     * Чужой и несуществующий ресурс отдают 403, а не 404: запрет стоит
     * раньше контроллера и не подсказывает, что именно лежит в базе.
     */
    public function test_forbidden_wins_over_not_found(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create();
        $foreign = $this->boardOf(EloquentUserModel::factory()->create());

        $this->actingAsUser($user)
            ->patchJson('/api/v1/directions/'.$foreign['direction']->id, ['title' => 'Чужое'])
            ->assertForbidden();

        $this->actingAsUser($user)
            ->deleteJson('/api/v1/tasks/'.$foreign['task']->id)
            ->assertForbidden();

        $this->actingAsUser($user)
            ->patchJson('/api/v1/tasks/999999', ['column' => 'in_progress'])
            ->assertForbidden();

        // Чужая доска не тронута.
        $this->assertDatabaseHas('directions', [
            'id' => $foreign['direction']->id,
            'title' => $foreign['direction']->title,
        ]);
        $this->assertDatabaseHas('tasks', ['id' => $foreign['task']->id]);
    }

    /**
     * Конфликт состояния (409) не должен «просвечивать» раньше режима:
     * пользователь не узнаёт, что направление или карточка непустые.
     */
    public function test_forbidden_wins_over_conflict(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create();
        $tree = $this->hierarchyOf($user);
        $board = $this->boardOf($user);

        // Направление с карточкой: без режима 409 (DirectionHasCards).
        $this->actingAsUser($user)
            ->deleteJson('/api/v1/directions/'.$board['direction']->id)
            ->assertForbidden();

        // Карточка с детьми: без режима 409 (TaskHasChildren).
        $this->actingAsUser($user)
            ->deleteJson('/api/v1/tasks/'.$tree['initiative']->id)
            ->assertForbidden();

        $this->actingAsUser($user)
            ->deleteJson('/api/v1/tasks/'.$tree['project']->id)
            ->assertForbidden();

        $this->assertDatabaseCount('tasks', 4);
        $this->assertDatabaseCount('directions', 2);
    }

    /**
     * PUT на маршрут, у которого есть только PATCH: 405 отдаёт роутер до
     * middleware, потому что такого эндпоинта нет вовсе. Фиксируем, чтобы
     * смена разрешённых методов не прошла незамеченной.
     */
    public function test_put_on_patch_only_route_is_method_not_allowed(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create();
        $board = $this->boardOf($user);

        $this->actingAsUser($user)
            ->putJson('/api/v1/tasks/'.$board['task']->id, ['column' => 'in_progress'])
            ->assertStatus(Response::HTTP_METHOD_NOT_ALLOWED);

        $this->actingAsUser($user)
            ->putJson('/api/v1/directions/'.$board['direction']->id, ['title' => 'Новое'])
            ->assertStatus(Response::HTTP_METHOD_NOT_ALLOWED);

        $this->assertDatabaseHas('tasks', [
            'id' => $board['task']->id,
            'column' => CardColumn::Backlog->value,
        ]);
    }

    // ------------------------------------------------------------------
    // Исключения: выход из аккаунта и методы чтения
    // ------------------------------------------------------------------

    public function test_readonly_user_keeps_read_access(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create();
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
        $user = EloquentUserModel::factory()->readonly()->create();
        $token = $user->createToken('phpunit');

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        // Токен отозван: повторный запрос с ним — уже гость.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * HEAD и OPTIONS — методы чтения: веб-клиент не смог бы ни узнать размер
     * ответа, ни пройти CORS-preflight, если бы режим их отсекал.
     */
    public function test_read_methods_other_than_get_are_allowed(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create();
        $this->boardOf($user);

        $this->actingAsUser($user)->head('/api/v1/tasks')->assertOk();

        // OPTIONS обрабатывает HandleCors; главное — не отказ режима.
        $this->actingAsUser($user)
            ->call('OPTIONS', '/api/v1/tasks')
            ->assertSuccessful();
    }

    // ------------------------------------------------------------------
    // Гость и обычный пользователь
    // ------------------------------------------------------------------

    /**
     * Гость — не read-only пользователь: 401 от auth:sanctum, а не 403,
     * иначе клиент показывал бы «режим только для чтения» до входа.
     */
    public function test_guest_mutations_get_unauthorized_not_forbidden(): void
    {
        $foreign = $this->boardOf(EloquentUserModel::factory()->create());

        foreach ($this->writeRequests($foreign['direction']->id, $foreign['task']->id) as $name => [$method, $uri, $payload]) {
            $this->json($method, $uri, $payload)
                ->assertUnauthorized()
                ->assertJsonMissingPath('errors.account');
        }

        $this->assertDatabaseCount('directions', 1);
        $this->assertDatabaseCount('tasks', 1);
    }

    // ------------------------------------------------------------------
    // Контракт ответа 403
    // ------------------------------------------------------------------

    public function test_forbidden_response_has_account_error_and_no_payload(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create();

        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/directions', ['title' => 'Здоровье', 'color' => 'c3']);

        $response->assertForbidden();

        // Ключ `account` — чтобы клиент показал текст рядом с действием,
        // а не потерял его в общем баннере. Полезных данных в отказе нет.
        $response->assertExactJson($this->expectedDenial());
        $response->assertJsonMissingPath('data');

        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }

    /**
     * Серия отказов не упирается в throttle: 403 — это не 429, счётчики
     * лимитеров на изменяющих маршрутах не расходуются.
     */
    public function test_repeated_forbidden_requests_stay_forbidden(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create();

        $this->actingAsUser($user);

        for ($attempt = 1; $attempt <= 12; $attempt++) {
            $this->postJson('/api/v1/directions', ['title' => 'Здоровье '.$attempt, 'color' => 'c3'])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('directions', ['user_id' => $user->id]);
    }

    // ------------------------------------------------------------------
    // Контракт поля readonly
    // ------------------------------------------------------------------

    /**
     * Данные текущего пользователя: флаг режима приходит, служебный флаг
     * администратора клиенту не отдаётся.
     */
    public function test_readonly_flag_is_exposed_in_current_user(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create();

        $this->actingAsUser($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.readonly', true)
            ->assertJsonMissingPath('data.is_admin');
    }

    public function test_readonly_flag_is_returned_on_mobile_login(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create(['password' => 'secret-password']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'phpunit',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.readonly', true);
    }

    public function test_readonly_flag_is_false_for_regular_user(): void
    {
        $user = EloquentUserModel::factory()->create(['password' => 'secret-password']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'phpunit',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.readonly', false);

        $this->actingAsUser($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.readonly', false);
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

    public function test_readonly_flag_is_not_settable_through_web_registration(): void
    {
        $this->withHeader('Origin', self::ORIGIN)
            ->postJson('/api/v1/auth/web/register', [
                'email' => 'web-intruder@example.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'readonly' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.readonly', false);

        $registered = User::query()->where('email', 'web-intruder@example.test')->firstOrFail();

        $this->assertFalse($registered->isReadonly());
    }

    /**
     * Массовое заполнение флаг не поднимает: поле не в $fillable, поэтому
     * update() молча его игнорирует. Менять режим можно только явно
     * (админка или forceFill), как и is_admin.
     */
    public function test_readonly_flag_is_not_mass_assignable(): void
    {
        $user = EloquentUserModel::factory()->create();

        $user->update(['readonly' => true]);
        self::assertFalse($user->fresh()->isReadonly());

        $user->forceFill(['readonly' => true])->save();
        self::assertTrue($user->fresh()->isReadonly());
    }

    // ------------------------------------------------------------------
    // Веб-канал: сессия + CSRF
    // ------------------------------------------------------------------

    public function test_web_channel_exposes_readonly_flag_and_keeps_read_access(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create(['password' => 'secret-password']);
        $session = $this->webSessionCookie($user);

        $this->withHeader('Origin', self::ORIGIN)
            ->withUnencryptedCookie(self::SESSION_COOKIE, $session)
            ->getJson('/api/v1/auth/web/me')
            ->assertOk()
            ->assertJsonPath('data.readonly', true);

        // Общий маршрут принимает сессию так же, как токен.
        $this->withHeader('Origin', self::ORIGIN)
            ->withUnencryptedCookie(self::SESSION_COOKIE, $session)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.readonly', true);
    }

    /**
     * Веб-клиент пишет сессией: запрет режима действует и в stateful-канале,
     * и заголовок X-XSRF-TOKEN (штатный для веб-клиента) ему не мешает.
     *
     * CSRF-проверка в юнит-тестах Laravel отключена (PreventRequestForgery
     * пропускает запросы при runningUnitTests()), поэтому 419 здесь не
     * воспроизводится — он проверен браузерным поведением и не относится
     * к режиму «только чтение».
     */
    public function test_web_channel_write_is_forbidden_with_csrf_header(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create(['password' => 'secret-password']);
        $session = $this->webSessionCookie($user);

        // Сервер сверяет значение cookie XSRF-TOKEN с заголовком X-XSRF-TOKEN.
        $this->withHeader('Origin', self::ORIGIN)
            ->withUnencryptedCookie(self::SESSION_COOKIE, $session)
            ->withUnencryptedCookie('XSRF-TOKEN', 'csrf-token-for-test')
            ->withHeader('X-XSRF-TOKEN', 'csrf-token-for-test')
            ->postJson('/api/v1/directions', ['title' => 'Здоровье', 'color' => 'c3'])
            ->assertForbidden()
            ->assertJsonPath('errors.account.0', ReadOnlyMode::denied()->getMessage());

        // Обновление и удаление в том же канале — тоже отказ.
        $this->withHeader('Origin', self::ORIGIN)
            ->withUnencryptedCookie(self::SESSION_COOKIE, $session)
            ->putJson('/api/v1/user/settings', ['settings' => ['board.theme' => '"dark"']])
            ->assertForbidden();

        $this->assertDatabaseMissing('directions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('user_meta', ['user_id' => $user->id]);
    }

    public function test_web_channel_logout_is_allowed_for_readonly_user(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create(['password' => 'secret-password']);
        $session = $this->webSessionCookie($user);

        $this->withHeader('Origin', self::ORIGIN)
            ->withUnencryptedCookie(self::SESSION_COOKIE, $session)
            ->postJson('/api/v1/auth/web/logout')
            ->assertNoContent();

        // Сессия погашена: старая cookie больше не авторизует.
        $this->withHeader('Origin', self::ORIGIN)
            ->withUnencryptedCookie(self::SESSION_COOKIE, $session)
            ->getJson('/api/v1/auth/web/me')
            ->assertUnauthorized();
    }

    public function test_command_bus_denies_writes_inside_http_request(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create();

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
        $admin = EloquentUserModel::factory()->admin()->create();
        $readonlyOwner = EloquentUserModel::factory()->readonly()->create();
        $direction = EloquentDirectionModel::factory()->create(['user_id' => $readonlyOwner->id]);

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

    /**
     * Администратор с флагом readonly: панель обязана работать (команды
     * админки идут без проверки), а его собственные записи через API — нет.
     * В панель он попадает тем же каналом, что и веб-клиент.
     */
    public function test_admin_with_readonly_flag_can_use_admin_commands_but_not_api_writes(): void
    {
        $readonlyAdmin = EloquentUserModel::factory()->admin()->readonly()->create();
        $owner = EloquentUserModel::factory()->create();
        $direction = EloquentDirectionModel::factory()->create(['user_id' => $owner->id]);

        Route::middleware('auth:sanctum')->post('/__test/admin-readonly-dispatch', function (Request $request): JsonResponse {
            app(CommandBus::class)->dispatch(new CreateTaskForAdminCommand(
                (int) $request->input('owner_id'),
                new CreateTaskCommand(
                    CardType::Initiative,
                    CardColumn::Backlog,
                    TaskTitle::fromString('Модерация от админа'),
                    directionId: (int) $request->input('direction_id'),
                ),
            ));

            return response()->json(['dispatched' => true]);
        });

        $this->actingAsUser($readonlyAdmin)
            ->postJson('/__test/admin-readonly-dispatch', [
                'owner_id' => $owner->id,
                'direction_id' => $direction->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('tasks', [
            'user_id' => $owner->id,
            'title' => 'Модерация от админа',
        ]);

        // Своя запись через API — по-прежнему 403.
        $this->actingAsUser($readonlyAdmin)
            ->postJson('/api/v1/directions', ['title' => 'Своё направление', 'color' => 'c3'])
            ->assertForbidden();
    }

    public function test_regular_user_is_not_affected(): void
    {
        $user = EloquentUserModel::factory()->create();

        $this->actingAsUser($user)
            ->postJson('/api/v1/directions', ['title' => 'Здоровье', 'color' => 'c3'])
            ->assertCreated();

        $this->assertDatabaseHas('directions', ['user_id' => $user->id, 'title' => 'Здоровье']);
    }

    // ------------------------------------------------------------------
    // Смена флага в рантайме
    // ------------------------------------------------------------------

    /**
     * Флаг читается из базы на каждом запросе: включение и снятие режима
     * действуют на уже выданном токене, без перелогина.
     */
    public function test_flag_changes_take_effect_on_existing_token(): void
    {
        $user = EloquentUserModel::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token);

        // Флаг включён администратором (эмуляция панели: поле не в $fillable).
        $user->forceFill(['readonly' => true])->save();

        $this->postJson('/api/v1/directions', ['title' => 'Здоровье', 'color' => 'c3'])
            ->assertForbidden();

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.readonly', true);

        // Флаг снят — запись снова доступна с тем же токеном.
        $user->forceFill(['readonly' => false])->save();

        $this->postJson('/api/v1/directions', ['title' => 'Здоровье', 'color' => 'c3'])
            ->assertCreated();

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.readonly', false);
    }

    /**
     * Выход из аккаунта не снимает режим: флаг принадлежит учётной записи,
     * а не сессии, поэтому после повторного входа он на месте.
     */
    public function test_logout_and_login_keep_the_flag(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create(['password' => 'secret-password']);

        $token = $user->createToken('phpunit')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'phpunit',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.readonly', true);
    }

    // ------------------------------------------------------------------
    // Не-API маршруты и системные адреса
    // ------------------------------------------------------------------

    /**
     * Запрет живёт в группе `api` и на публичный сайт не распространяется:
     * лендинг и юридические страницы открываются всем, включая read-only
     * пользователя с токеном.
     */
    public function test_public_site_is_not_affected(): void
    {
        foreach (['/', '/privacy', '/terms', '/cookies'] as $uri) {
            $this->get($uri)->assertOk();
        }

        $this->actingAsUser(EloquentUserModel::factory()->readonly()->create());

        foreach (['/', '/privacy', '/terms', '/cookies'] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    /**
     * Методы без страницы на публичных адресах отдают 405 от роутера:
     * режим здесь ни при чём, и это не 403 и не 500.
     */
    public function test_write_methods_on_public_site_are_method_not_allowed(): void
    {
        $cases = [['DELETE', '/'], ['POST', '/'], ['DELETE', '/admin']];

        foreach ($cases as [$method, $uri]) {
            $this->call($method, $uri)
                ->assertStatus(Response::HTTP_METHOD_NOT_ALLOWED);
        }

        $this->actingAsUser(EloquentUserModel::factory()->readonly()->create());

        foreach ($cases as [$method, $uri]) {
            $this->call($method, $uri)
                ->assertStatus(Response::HTTP_METHOD_NOT_ALLOWED);
        }
    }

    /**
     * Служебные адреса не задеты: проверка здоровья и /up доступны всегда.
     */
    public function test_service_endpoints_stay_available(): void
    {
        $this->getJson('/api/v1/health')->assertOk();

        $this->call('GET', '/up')->assertOk();

        $this->actingAsUser(EloquentUserModel::factory()->readonly()->create())
            ->getJson('/api/v1/health')
            ->assertOk();
    }
}
