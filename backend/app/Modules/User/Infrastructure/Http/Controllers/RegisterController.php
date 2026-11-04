<?php

namespace App\Modules\User\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Application\Commands\RegisterUserCommand;
use App\Modules\User\Application\UseCases\RegisterUserUseCase;
use App\Modules\User\Infrastructure\Http\Requests\RegisterRequest;
use App\Modules\User\Infrastructure\Http\Resources\AuthTokenResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Входной адаптер модуля User: регистрация по email и паролю.
 *
 * Контроллер только переводит HTTP-запрос в команду прикладного слоя
 * и оборачивает результат в ресурс — бизнес-логики здесь нет.
 */
final class RegisterController extends Controller
{
    public function __construct(
        private readonly RegisterUserUseCase $registerUser,
    ) {}

    /**
     * Зарегистрировать пользователя и выдать Bearer-токен.
     */
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $result = $this->registerUser->execute(
            RegisterUserCommand::fromArray($request->toCommandPayload()),
        );

        return (new AuthTokenResource($result->user))
            ->withToken($result->plainTextToken)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
