<?php

namespace App\Modules\User\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Application\Commands\RegisterUser\RegisterUserCommand;
use App\Modules\User\Application\Results\RegistrationResult;
use App\Modules\User\Infrastructure\Http\Requests\RegisterRequest;
use App\Modules\User\Infrastructure\Http\Resources\AuthTokenResource;
use App\Services\AuthService;
use App\Shared\Application\Bus\CommandBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Входной адаптер модуля User: регистрация по email и паролю (мобильный клиент).
 *
 * Контроллер только переводит HTTP-запрос в команду прикладного слоя,
 * отправляет её в шину, выдаёт Bearer-токен и оборачивает результат в ресурс —
 * бизнес-логики здесь нет. Веб-клиент регистрируется через
 * POST /api/v1/auth/web/register и получает сессию.
 */
final class RegisterController extends Controller
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly AuthService $auth,
    ) {}

    /**
     * Зарегистрировать пользователя и выдать Bearer-токен.
     */
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        /** @var RegistrationResult $result */
        $result = $this->bus->dispatch(
            RegisterUserCommand::fromArray($request->toCommandPayload()),
        );

        // Имя устройства нужно только для токена: клиент может прислать его
        // при регистрации, иначе токен помечается как мобильный.
        $deviceName = $request->string('device_name')->toString() ?: 'mobile';

        return (new AuthTokenResource($result->user))
            ->withToken($this->auth->issueToken($result->user, $deviceName))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
