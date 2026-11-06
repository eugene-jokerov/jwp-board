<?php

namespace App\Modules\User\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Application\Commands\DeleteUserSettings\DeleteUserSettingsCommand;
use App\Modules\User\Domain\User;
use App\Modules\User\Domain\ValueObjects\MetaKey;
use App\Shared\Application\Bus\CommandBus;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Удаление настройки текущего пользователя — сброс к значению по умолчанию.
 *
 * Несуществующая настройка не считается ошибкой: результат для клиента один
 * и тот же («настройка не сохранена»), поэтому ответ всегда 204.
 */
final class DeleteUserSettingsController extends Controller
{
    public function __construct(
        private readonly CommandBus $bus,
    ) {}

    /**
     * DELETE /api/v1/user/settings/{key}
     */
    public function __invoke(Request $request, string $key): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->bus->dispatch(
            new DeleteUserSettingsCommand((int) $user->getKey(), MetaKey::fromString($key)),
        );

        return response()->noContent();
    }
}
