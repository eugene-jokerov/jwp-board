<?php

namespace App\Modules\User\Infrastructure\Http\Resources;

use App\Modules\User\Domain\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ответ регистрации: токен доступа и данные пользователя.
 *
 * @mixin User
 */
final class AuthTokenResource extends JsonResource
{
    /**
     * Тип токена: мобильный клиент подставляет его в заголовок Authorization.
     */
    public const string TOKEN_TYPE = 'Bearer';

    private string $plainTextToken = '';

    /**
     * Передать plain-text токен: он существует только в момент выдачи.
     */
    public function withToken(string $plainTextToken): self
    {
        $this->plainTextToken = $plainTextToken;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->plainTextToken,
            'token_type' => self::TOKEN_TYPE,
            'user' => new UserResource($this->resource),
        ];
    }
}
