<?php

namespace App\Shared\Infrastructure\Providers;

use App\Modules\Direction\Application\Commands\CreateDirection\CreateDirectionCommand;
use App\Modules\Direction\Application\Commands\CreateDirection\CreateDirectionCommandHandler;
use App\Modules\Direction\Application\Commands\CreateDirectionForAdmin\CreateDirectionForAdminCommand;
use App\Modules\Direction\Application\Commands\CreateDirectionForAdmin\CreateDirectionForAdminCommandHandler;
use App\Modules\Direction\Application\Commands\DeleteDirection\DeleteDirectionCommand;
use App\Modules\Direction\Application\Commands\DeleteDirection\DeleteDirectionCommandHandler;
use App\Modules\Direction\Application\Commands\DeleteDirectionForAdmin\DeleteDirectionForAdminCommand;
use App\Modules\Direction\Application\Commands\DeleteDirectionForAdmin\DeleteDirectionForAdminCommandHandler;
use App\Modules\Direction\Application\Commands\UpdateDirection\UpdateDirectionCommand;
use App\Modules\Direction\Application\Commands\UpdateDirection\UpdateDirectionCommandHandler;
use App\Modules\Direction\Application\Commands\UpdateDirectionForAdmin\UpdateDirectionForAdminCommand;
use App\Modules\Direction\Application\Commands\UpdateDirectionForAdmin\UpdateDirectionForAdminCommandHandler;
use App\Modules\Direction\Application\Queries\ListDirections\ListDirectionsQuery;
use App\Modules\Direction\Application\Queries\ListDirections\ListDirectionsQueryHandler;
use App\Modules\Direction\Application\Queries\ShowDirection\ShowDirectionQuery;
use App\Modules\Direction\Application\Queries\ShowDirection\ShowDirectionQueryHandler;
use App\Modules\Task\Application\Commands\CreateTask\CreateTaskCommand;
use App\Modules\Task\Application\Commands\CreateTask\CreateTaskCommandHandler;
use App\Modules\Task\Application\Commands\CreateTaskForAdmin\CreateTaskForAdminCommand;
use App\Modules\Task\Application\Commands\CreateTaskForAdmin\CreateTaskForAdminCommandHandler;
use App\Modules\Task\Application\Commands\DeleteTask\DeleteTaskCommand;
use App\Modules\Task\Application\Commands\DeleteTask\DeleteTaskCommandHandler;
use App\Modules\Task\Application\Commands\DeleteTaskForAdmin\DeleteTaskForAdminCommand;
use App\Modules\Task\Application\Commands\DeleteTaskForAdmin\DeleteTaskForAdminCommandHandler;
use App\Modules\Task\Application\Commands\UpdateTask\UpdateTaskCommand;
use App\Modules\Task\Application\Commands\UpdateTask\UpdateTaskCommandHandler;
use App\Modules\Task\Application\Commands\UpdateTaskForAdmin\UpdateTaskForAdminCommand;
use App\Modules\Task\Application\Commands\UpdateTaskForAdmin\UpdateTaskForAdminCommandHandler;
use App\Modules\Task\Application\Queries\ListTasks\ListTasksQuery;
use App\Modules\Task\Application\Queries\ListTasks\ListTasksQueryHandler;
use App\Modules\Task\Application\Queries\ShowTask\ShowTaskQuery;
use App\Modules\Task\Application\Queries\ShowTask\ShowTaskQueryHandler;
use App\Modules\User\Application\Commands\DeleteUserSettings\DeleteUserSettingsCommand;
use App\Modules\User\Application\Commands\DeleteUserSettings\DeleteUserSettingsCommandHandler;
use App\Modules\User\Application\Commands\RegisterUser\RegisterUserCommand;
use App\Modules\User\Application\Commands\RegisterUser\RegisterUserCommandHandler;
use App\Modules\User\Application\Commands\UpdateUserSettings\UpdateUserSettingsCommand;
use App\Modules\User\Application\Commands\UpdateUserSettings\UpdateUserSettingsCommandHandler;
use App\Modules\User\Application\Queries\CurrentUser\CurrentUserQuery;
use App\Modules\User\Application\Queries\CurrentUser\CurrentUserQueryHandler;
use App\Modules\User\Application\Queries\ListUserSettings\ListUserSettingsQuery;
use App\Modules\User\Application\Queries\ListUserSettings\ListUserSettingsQueryHandler;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\LaravelCommandBus;
use App\Shared\Application\Bus\LaravelQueryBus;
use App\Shared\Application\Bus\Middleware\DenyReadOnlyCommands;
use App\Shared\Application\Bus\Middleware\TransactionMiddleware;
use App\Shared\Application\Bus\QueryBus;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\ServiceProvider;

/**
 * Шина приложения: привязки CommandBus/QueryBus и карта «сообщение → хендлер».
 *
 * Вся CQRS-инфраструктура собрана в одном месте: чтобы понять, какой хендлер
 * обслуживает команду или запрос, достаточно посмотреть константы ниже.
 */
final class BusServiceProvider extends ServiceProvider
{
    /**
     * Карта команд: команда → командный хендлер.
     *
     * @var array<class-string, class-string>
     */
    private const array COMMAND_HANDLERS = [
        RegisterUserCommand::class => RegisterUserCommandHandler::class,
        UpdateUserSettingsCommand::class => UpdateUserSettingsCommandHandler::class,
        DeleteUserSettingsCommand::class => DeleteUserSettingsCommandHandler::class,
        CreateDirectionCommand::class => CreateDirectionCommandHandler::class,
        UpdateDirectionCommand::class => UpdateDirectionCommandHandler::class,
        DeleteDirectionCommand::class => DeleteDirectionCommandHandler::class,
        CreateTaskCommand::class => CreateTaskCommandHandler::class,
        UpdateTaskCommand::class => UpdateTaskCommandHandler::class,
        DeleteTaskCommand::class => DeleteTaskCommandHandler::class,
        // Команды админки (/admin): те же сценарии, но владельца карточки или
        // направления выбирает администратор, а не аутентификация.
        CreateDirectionForAdminCommand::class => CreateDirectionForAdminCommandHandler::class,
        UpdateDirectionForAdminCommand::class => UpdateDirectionForAdminCommandHandler::class,
        DeleteDirectionForAdminCommand::class => DeleteDirectionForAdminCommandHandler::class,
        CreateTaskForAdminCommand::class => CreateTaskForAdminCommandHandler::class,
        UpdateTaskForAdminCommand::class => UpdateTaskForAdminCommandHandler::class,
        DeleteTaskForAdminCommand::class => DeleteTaskForAdminCommandHandler::class,
    ];

    /**
     * Карта запросов: запрос → хендлер запроса.
     *
     * @var array<class-string, class-string>
     */
    private const array QUERY_HANDLERS = [
        CurrentUserQuery::class => CurrentUserQueryHandler::class,
        ListUserSettingsQuery::class => ListUserSettingsQueryHandler::class,
        ListDirectionsQuery::class => ListDirectionsQueryHandler::class,
        ShowDirectionQuery::class => ShowDirectionQueryHandler::class,
        ListTasksQuery::class => ListTasksQueryHandler::class,
        ShowTaskQuery::class => ShowTaskQueryHandler::class,
    ];

    /**
     * Пайпы команд: команда → набор пайпов.
     *
     * По умолчанию — транзакция и запрет записи в режиме «только чтение»
     * (WRITE_PIPES). Исключения:
     *  - команды админки (/admin) — только транзакция: администратор правит
     *    доски read-only пользователей из панели, и флаг их учётки не помеха;
     *  - регистрация — тоже только транзакция: новой учётной записи ещё нет,
     *    а гость под флагом предыдущей сессии не должен блокировать регистрацию.
     *
     * Набор задаётся явно, а не условием внутри пайпа: тогда правило видно
     * прямо в карте, а не в коде проверки.
     *
     * @var array<class-string, array<int, class-string>>
     */
    private const array COMMAND_PIPES = [
        RegisterUserCommand::class => self::PLAIN_PIPES,
        CreateDirectionCommand::class => self::WRITE_PIPES,
        UpdateDirectionCommand::class => self::WRITE_PIPES,
        DeleteDirectionCommand::class => self::WRITE_PIPES,
        CreateTaskCommand::class => self::WRITE_PIPES,
        UpdateTaskCommand::class => self::WRITE_PIPES,
        DeleteTaskCommand::class => self::WRITE_PIPES,
        UpdateUserSettingsCommand::class => self::WRITE_PIPES,
        DeleteUserSettingsCommand::class => self::WRITE_PIPES,
        CreateDirectionForAdminCommand::class => self::PLAIN_PIPES,
        UpdateDirectionForAdminCommand::class => self::PLAIN_PIPES,
        DeleteDirectionForAdminCommand::class => self::PLAIN_PIPES,
        CreateTaskForAdminCommand::class => self::PLAIN_PIPES,
        UpdateTaskForAdminCommand::class => self::PLAIN_PIPES,
        DeleteTaskForAdminCommand::class => self::PLAIN_PIPES,
    ];

    /**
     * Пайпы команд из API: транзакция и запрет записи в режиме «только чтение».
     *
     * Порядок важен: транзакция идёт первой, чтобы проверка права на запись
     * выполнялась внутри неё и отказ откатывал запрос атомарно.
     *
     * Этот же набор получают команды, которых нет в карте COMMAND_PIPES:
     * безопасное поведение по умолчанию — транзакция плюс проверка прав.
     *
     * @var array<int, class-string>
     */
    private const array WRITE_PIPES = [
        TransactionMiddleware::class,
        DenyReadOnlyCommands::class,
    ];

    /**
     * Пайпы без проверки права на запись: только транзакция.
     *
     * @var array<int, class-string>
     */
    private const array PLAIN_PIPES = [
        TransactionMiddleware::class,
    ];

    /**
     * Регистрация шин в контейнере.
     */
    public function register(): void
    {
        $this->app->bind(CommandBus::class, fn ($app): LaravelCommandBus => new LaravelCommandBus(
            // Конвейер создаётся на каждый dispatch: Pipeline хранит состояние вызова.
            $app->make(Pipeline::class),
            $app->make(DispatcherContract::class),
            self::COMMAND_PIPES,
            self::WRITE_PIPES,
        ));

        // Запросы только читают данные: транзакционного пайпа у них нет.
        $this->app->bind(QueryBus::class, fn ($app): LaravelQueryBus => new LaravelQueryBus(
            $app->make(Pipeline::class),
            self::QUERY_HANDLERS,
        ));
    }

    /**
     * Регистрация карты команд: Laravel ищет хендлер по ней, без конвенций имён.
     */
    public function boot(): void
    {
        Bus::map(self::COMMAND_HANDLERS);
    }
}
