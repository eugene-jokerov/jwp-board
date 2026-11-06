<?php

namespace App\Modules\User\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Application\Commands\UpdateUserSettings\UpdateUserSettingsCommand;
use App\Modules\User\Domain\User;
use App\Modules\User\Infrastructure\Http\Requests\UpdateUserSettingsRequest;
use App\Modules\User\Infrastructure\Http\Resources\UserSettingResource;
use App\Shared\Application\Bus\CommandBus;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Сохранение настроек текущего пользователя.
 *
 * Клиент присылает объект `settings` с изменившимися парами; каждая
 * сохраняется UPSERT-ом, а в ответ приходит полное состояние пользователя.
 * Так клиенту не нужно отдельно знать, что уже было сохранено раньше.
 */
final class UpdateUserSettingsController extends Controller
{
    public function __construct(
        private readonly CommandBus $bus,
    ) {}

    /**
     * PUT /api/v1/user/settings
     */
    public function __invoke(UpdateUserSettingsRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array<string, string|null> $settings */
        $settings = $this->bus->dispatch(
            UpdateUserSettingsCommand::fromArray((int) $user->getKey(), $request->toCommandPayload()),
        );

        $resources = [];

        foreach ($settings as $key => $value) {
            $resources[] = UserSettingResource::forSetting($key, $value);
        }

        return UserSettingResource::collection($resources);
    }
}
