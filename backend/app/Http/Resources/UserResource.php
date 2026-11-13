<?php

namespace App\Http\Resources;

use App\Modules\User\Domain\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Публичное представление пользователя в ответах API.
 *
 * `readonly` — режим «только чтение»: клиент показывает по нему плашку и гасит
 * действия, а сервер отвечает 403 на любой изменяющий запрос. Поле приходит
 * во всех ответах с пользователем (вход, регистрация, /auth/me), поэтому флаг
 * всегда актуален сразу после входа.
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'readonly' => $this->isReadonly(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
