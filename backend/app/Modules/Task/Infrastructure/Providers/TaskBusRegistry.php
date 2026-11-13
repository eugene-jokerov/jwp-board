<?php

namespace App\Modules\Task\Infrastructure\Providers;

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
use App\Shared\Application\Bus\DependencyInjection\BusRegistry;

/**
 * Сценарии модуля Task в шине приложения.
 *
 * Карта «команда/запрос → хендлер» принадлежит модулю: шина лишь собирает
 * карты из config('bus.registries').
 *
 * Команды админки (/admin) выполняются только в транзакции: администратор
 * правит доски read-only пользователей из панели, и флаг их учётки не помеха.
 */
final readonly class TaskBusRegistry implements BusRegistry
{
    private const array COMMAND_HANDLERS = [
        CreateTaskCommand::class => CreateTaskCommandHandler::class,
        UpdateTaskCommand::class => UpdateTaskCommandHandler::class,
        DeleteTaskCommand::class => DeleteTaskCommandHandler::class,
        CreateTaskForAdminCommand::class => CreateTaskForAdminCommandHandler::class,
        UpdateTaskForAdminCommand::class => UpdateTaskForAdminCommandHandler::class,
        DeleteTaskForAdminCommand::class => DeleteTaskForAdminCommandHandler::class,
    ];

    private const array QUERY_HANDLERS = [
        ListTasksQuery::class => ListTasksQueryHandler::class,
        ShowTaskQuery::class => ShowTaskQueryHandler::class,
    ];

    private const array PLAIN_COMMANDS = [
        CreateTaskForAdminCommand::class,
        UpdateTaskForAdminCommand::class,
        DeleteTaskForAdminCommand::class,
    ];

    public function commands(): array
    {
        return self::COMMAND_HANDLERS;
    }

    public function queries(): array
    {
        return self::QUERY_HANDLERS;
    }

    public function plainCommands(): array
    {
        return self::PLAIN_COMMANDS;
    }
}
