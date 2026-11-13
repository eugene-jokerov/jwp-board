<?php

namespace App\Http\Controllers\Api\V1\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Modules\User\Application\Commands\LoginUser\LoginUserCommand;
use App\Modules\User\Application\Commands\RegisterUser\RegisterUserCommand;
use App\Modules\User\Application\Queries\CurrentUser\CurrentUserQuery;
use App\Modules\User\Application\Results\LoginResult;
use App\Modules\User\Application\Results\RegistrationResult;
use App\Modules\User\Domain\User;
use App\Modules\User\Domain\ValueObjects\Email;
use App\Modules\User\Infrastructure\Session\WebSessionService;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Аутентификация веб-клиента: сессия вместо Bearer-токена.
 *
 * Роуты живут под /api/v1/auth/web. Ответы не содержат токена — входное
 * доказательство едет в HttpOnly-cookie сессии Laravel (Sanctum ставит её
 * для stateful-запросов). Мобильные клиенты используют /api/v1/auth/login
 * и /api/v1/user/register. Запись идёт командой через CommandBus,
 * чтение — запросом через QueryBus.
 */
final class AuthController extends Controller
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly WebSessionService $sessions,
    ) {}

    /**
     * Войти по email и паролю, открыв сессию.
     */
    public function login(LoginRequest $request): JsonResponse|UserResource
    {
        /** @var LoginResult|null $result */
        $result = $this->commands->dispatch(new LoginUserCommand(
            Email::fromString($request->string('email')->toString()),
            $request->string('password')->toString(),
            'web',
        ));

        if ($result === null) {
            return response()->json([
                'message' => 'Неверный email или пароль.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $this->sessions->begin($result->user, $request);

        return new UserResource($result->user);
    }

    /**
     * Зарегистрировать пользователя и сразу открыть сессию.
     */
    public function register(StoreUserRequest $request): UserResource
    {
        /** @var RegistrationResult $result */
        $result = $this->commands->dispatch(
            RegisterUserCommand::fromArray($request->toCommandPayload()),
        );

        $this->sessions->begin($result->user, $request);

        return new UserResource($result->user);
    }

    /**
     * Данные текущего пользователя по сессии.
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
     * Выйти: закрыть сессию и погасить cookie.
     */
    public function logout(Request $request): Response
    {
        $this->sessions->end($request);

        return response()->noContent();
    }
}
