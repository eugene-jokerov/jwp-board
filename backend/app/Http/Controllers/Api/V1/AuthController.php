<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\UserResource;
use App\Modules\User\Application\Commands\LoginUser\LoginUserCommand;
use App\Modules\User\Application\Queries\CurrentUser\CurrentUserQuery;
use App\Modules\User\Application\Results\LoginResult;
use App\Modules\User\Domain\User;
use App\Modules\User\Domain\ValueObjects\Email;
use App\Modules\User\Infrastructure\Http\Resources\AuthTokenResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Аутентификация мобильного клиента по токенам Sanctum.
 *
 * Вход — сценарий шины (LoginUser): проверка учётных данных и выдача токена
 * живут в модуле User, контроллер только переводит HTTP в команду.
 * Регистрация пользователя тоже живёт в модуле User: POST /api/v1/user/register.
 */
final class AuthController extends Controller
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {}

    /**
     * Выдать токен по email и паролю.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $deviceName = $request->string('device_name')->toString() ?: 'mobile';

        /** @var LoginResult|null $result */
        $result = $this->commands->dispatch(new LoginUserCommand(
            Email::fromString($request->string('email')->toString()),
            $request->string('password')->toString(),
            $deviceName,
        ));

        if ($result === null) {
            return response()->json([
                'message' => 'Неверный email или пароль.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return (new AuthTokenResource($result->user))
            ->withToken($result->plainTextToken)
            ->response();
    }

    /**
     * Данные текущего пользователя.
     */
    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        /** @var User $current */
        $current = $this->queries->ask(new CurrentUserQuery((int) $user->getKey()));

        return new UserResource($current);
    }

    /**
     * Отозвать текущий токен.
     */
    public function logout(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $user->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
