<?php

namespace App\Http\Controllers\Api\V1\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Modules\User\Application\Commands\RegisterUserCommand;
use App\Modules\User\Application\UseCases\RegisterUserUseCase;
use App\Modules\User\Domain\User;
use App\Modules\User\Domain\ValueObjects\Email;
use App\Modules\User\Infrastructure\Http\Requests\RegisterRequest;
use App\Modules\User\Infrastructure\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\WebSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Аутентификация веб-клиента: сессия вместо Bearer-токена.
 *
 * Роуты живут под /api/v1/auth/web. Ответы не содержат токена — входное
 * доказательство едет в HttpOnly-cookie сессии Laravel (Sanctum ставит её
 * для stateful-запросов). Мобильные клиенты используют /api/v1/auth/login
 * и /api/v1/user/register.
 */
final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly RegisterUserUseCase $registerUser,
        private readonly WebSessionService $sessions,
    ) {}

    /**
     * Войти по email и паролю, открыв сессию.
     */
    public function login(LoginRequest $request): JsonResponse|UserResource
    {
        $user = $this->auth->attempt(
            Email::fromString($request->string('email')->toString()),
            $request->string('password')->toString(),
        );

        if ($user === null) {
            return response()->json([
                'message' => 'Неверный email или пароль.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $this->sessions->begin($user, $request);

        return new UserResource($user);
    }

    /**
     * Зарегистрировать пользователя и сразу открыть сессию.
     */
    public function register(RegisterRequest $request): UserResource
    {
        $result = $this->registerUser->execute(
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

        return new UserResource($user);
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
