<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Modules\User\Domain\User;
use App\Modules\User\Domain\ValueObjects\Email;
use App\Modules\User\Infrastructure\Http\Resources\AuthTokenResource;
use App\Modules\User\Infrastructure\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Аутентификация мобильного клиента по токенам Sanctum.
 *
 * Регистрация пользователя живёт в модуле User: POST /api/v1/user/register.
 */
final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    /**
     * Выдать токен по email и паролю.
     */
    public function login(LoginRequest $request): JsonResponse
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

        $deviceName = $request->string('device_name')->toString() ?: 'mobile';

        return (new AuthTokenResource($user))
            ->withToken($this->auth->issueToken($user, $deviceName))
            ->response();
    }

    /**
     * Данные текущего пользователя.
     */
    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user);
    }

    /**
     * Отозвать текущий токен.
     */
    public function logout(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->auth->revokeCurrentToken($user);

        return response()->noContent();
    }
}
