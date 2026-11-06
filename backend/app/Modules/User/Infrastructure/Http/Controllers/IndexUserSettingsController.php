<?php

namespace App\Modules\User\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Application\Queries\ListUserSettings\ListUserSettingsQuery;
use App\Modules\User\Domain\User;
use App\Modules\User\Infrastructure\Http\Resources\UserSettingResource;
use App\Shared\Application\Bus\QueryBus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Настройки текущего пользователя: свёрнутые уровни и колонки доски и прочее.
 *
 * Клиент получает все пары «ключ — значение» одним запросом: настроек мало,
 * а экран нуждается в них сразу. Своей логики у контроллера нет — он собирает
 * запрос, спрашивает шину и оборачивает ответ в ресурс.
 */
final class IndexUserSettingsController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
    ) {}

    /**
     * GET /api/v1/user/settings
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array<string, string|null> $settings */
        $settings = $this->queries->ask(new ListUserSettingsQuery((int) $user->getKey()));

        $resources = [];

        foreach ($settings as $key => $value) {
            $resources[] = UserSettingResource::forSetting($key, $value);
        }

        return UserSettingResource::collection($resources);
    }
}
