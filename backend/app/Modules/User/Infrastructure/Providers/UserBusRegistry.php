<?php

namespace App\Modules\User\Infrastructure\Providers;

use App\Modules\User\Application\Commands\DeleteUserSettings\DeleteUserSettingsCommand;
use App\Modules\User\Application\Commands\DeleteUserSettings\DeleteUserSettingsCommandHandler;
use App\Modules\User\Application\Commands\IssueBearerToken\IssueBearerTokenCommand;
use App\Modules\User\Application\Commands\IssueBearerToken\IssueBearerTokenCommandHandler;
use App\Modules\User\Application\Commands\LoginUser\LoginUserCommand;
use App\Modules\User\Application\Commands\LoginUser\LoginUserCommandHandler;
use App\Modules\User\Application\Commands\RegisterUser\RegisterUserCommand;
use App\Modules\User\Application\Commands\RegisterUser\RegisterUserCommandHandler;
use App\Modules\User\Application\Commands\UpdateUserSettings\UpdateUserSettingsCommand;
use App\Modules\User\Application\Commands\UpdateUserSettings\UpdateUserSettingsCommandHandler;
use App\Modules\User\Application\Queries\CurrentUser\CurrentUserQuery;
use App\Modules\User\Application\Queries\CurrentUser\CurrentUserQueryHandler;
use App\Modules\User\Application\Queries\ListUserSettings\ListUserSettingsQuery;
use App\Modules\User\Application\Queries\ListUserSettings\ListUserSettingsQueryHandler;
use App\Shared\Application\Bus\DependencyInjection\BusRegistry;

/**
 * Сценарии модуля User в шине приложения.
 *
 * Карта «команда/запрос → хендлер» принадлежит модулю: шина лишь собирает
 * карты из config('bus.registries').
 *
 * Регистрация и вход выполняются только в транзакции: новой учётной записи
 * ещё нет, а гость под флагом предыдущей сессии не должен блокировать вход.
 */
final readonly class UserBusRegistry implements BusRegistry
{
    private const array COMMAND_HANDLERS = [
        RegisterUserCommand::class => RegisterUserCommandHandler::class,
        UpdateUserSettingsCommand::class => UpdateUserSettingsCommandHandler::class,
        DeleteUserSettingsCommand::class => DeleteUserSettingsCommandHandler::class,
        LoginUserCommand::class => LoginUserCommandHandler::class,
        IssueBearerTokenCommand::class => IssueBearerTokenCommandHandler::class,
    ];

    private const array QUERY_HANDLERS = [
        CurrentUserQuery::class => CurrentUserQueryHandler::class,
        ListUserSettingsQuery::class => ListUserSettingsQueryHandler::class,
    ];

    private const array PLAIN_COMMANDS = [
        RegisterUserCommand::class,
        LoginUserCommand::class,
        IssueBearerTokenCommand::class,
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
