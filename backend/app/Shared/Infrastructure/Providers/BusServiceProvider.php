<?php

namespace App\Shared\Infrastructure\Providers;

use App\Modules\Direction\Application\Commands\CreateDirection\CreateDirectionCommand;
use App\Modules\Direction\Application\Commands\CreateDirection\CreateDirectionCommandHandler;
use App\Modules\Direction\Application\Commands\DeleteDirection\DeleteDirectionCommand;
use App\Modules\Direction\Application\Commands\DeleteDirection\DeleteDirectionCommandHandler;
use App\Modules\Direction\Application\Commands\UpdateDirection\UpdateDirectionCommand;
use App\Modules\Direction\Application\Commands\UpdateDirection\UpdateDirectionCommandHandler;
use App\Modules\Direction\Application\Queries\ListDirections\ListDirectionsQuery;
use App\Modules\Direction\Application\Queries\ListDirections\ListDirectionsQueryHandler;
use App\Modules\Direction\Application\Queries\ShowDirection\ShowDirectionQuery;
use App\Modules\Direction\Application\Queries\ShowDirection\ShowDirectionQueryHandler;
use App\Modules\Task\Application\Commands\CreateTask\CreateTaskCommand;
use App\Modules\Task\Application\Commands\CreateTask\CreateTaskCommandHandler;
use App\Modules\Task\Application\Commands\DeleteTask\DeleteTaskCommand;
use App\Modules\Task\Application\Commands\DeleteTask\DeleteTaskCommandHandler;
use App\Modules\Task\Application\Commands\UpdateTask\UpdateTaskCommand;
use App\Modules\Task\Application\Commands\UpdateTask\UpdateTaskCommandHandler;
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
     * Пайпы, применяемые к каждой команде.
     *
     * @var array<int, class-string>
     */
    private const array COMMAND_PIPES = [
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
