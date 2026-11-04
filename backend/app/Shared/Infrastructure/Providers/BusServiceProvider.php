<?php

namespace App\Shared\Infrastructure\Providers;

use App\Modules\User\Application\Commands\RegisterUser\RegisterUserCommand;
use App\Modules\User\Application\Commands\RegisterUser\RegisterUserCommandHandler;
use App\Modules\User\Application\Queries\CurrentUser\CurrentUserQuery;
use App\Modules\User\Application\Queries\CurrentUser\CurrentUserQueryHandler;
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
    ];

    /**
     * Карта запросов: запрос → хендлер запроса.
     *
     * @var array<class-string, class-string>
     */
    private const array QUERY_HANDLERS = [
        CurrentUserQuery::class => CurrentUserQueryHandler::class,
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
