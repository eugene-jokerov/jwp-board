<?php

namespace App\Modules\Direction\Infrastructure\Providers;

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
use App\Shared\Application\Bus\DependencyInjection\BusRegistry;

/**
 * Сценарии модуля Direction в шине приложения.
 *
 * Карта «команда/запрос → хендлер» принадлежит модулю: шина лишь собирает
 * карты из config('bus.registries').
 *
 * Команды админки (/admin) выполняются только в транзакции: администратор
 * правит доски read-only пользователей из панели, и флаг их учётки не помеха.
 */
final readonly class DirectionBusRegistry implements BusRegistry
{
    private const array COMMAND_HANDLERS = [
        CreateDirectionCommand::class => CreateDirectionCommandHandler::class,
        UpdateDirectionCommand::class => UpdateDirectionCommandHandler::class,
        DeleteDirectionCommand::class => DeleteDirectionCommandHandler::class,
        CreateDirectionForAdminCommand::class => CreateDirectionForAdminCommandHandler::class,
        UpdateDirectionForAdminCommand::class => UpdateDirectionForAdminCommandHandler::class,
        DeleteDirectionForAdminCommand::class => DeleteDirectionForAdminCommandHandler::class,
    ];

    private const array QUERY_HANDLERS = [
        ListDirectionsQuery::class => ListDirectionsQueryHandler::class,
        ShowDirectionQuery::class => ShowDirectionQueryHandler::class,
    ];

    private const array PLAIN_COMMANDS = [
        CreateDirectionForAdminCommand::class,
        UpdateDirectionForAdminCommand::class,
        DeleteDirectionForAdminCommand::class,
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
