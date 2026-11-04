<?php

namespace App\Modules\User\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Application\Commands\RegisterUserCommand;
use App\Modules\User\Application\UseCases\RegisterUserUseCase;
use App\Modules\User\Infrastructure\Http\Requests\RegisterRequest;
use App\Modules\User\Infrastructure\Http\Resources\AuthTokenResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Входной адаптер модуля User: регистрация по email и паролю (мобильный клиент).
 *
 * Контроллер только переводит HTTP-запрос в команду прикладного слоя,
 * выдаёт Bearer-токен и оборачивает результат в ресурс — бизнес-логики здесь нет.
 * Веб-клиент регистрируется через POST /api/v1/auth/web/register и получает сессию.
 */
final class RegisterController extends Controller
{
    public function __construct(
        private readonly RegisterUserUseCase $registerUser,
        private readonly AuthService $auth,
    ) {}

    /**
     * Зарегистрировать пользователя и выдать Bearer-токен.
     */
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $payload = $request->toCommandPayload();
        $result = $this->registerUser->execute(RegisterUserCommand::fromArray($payload));

        return (new AuthTokenResource($result->user))
            ->withToken($this->auth->issueToken($result->user, $payload['device_name'] ?: 'mobile'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
