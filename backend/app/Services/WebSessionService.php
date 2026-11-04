<?php

namespace App\Services;

use App\Modules\User\Domain\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Сессионная аутентификация веб-клиента.
 *
 * Единственное место, где приложение работает с сессией Laravel: контроллер
 * веб-аутентификации знает только про этот сервис, а мобильный вход сюда
 * не обращается вовсе (там Bearer-токен).
 */
final readonly class WebSessionService
{
    /**
     * Открыть сессию для пользователя.
     *
     * regenerate() защищает от session fixation: идентификатор, который был
     * у анонимного посетителя, после входа больше не действует.
     */
    public function begin(User $user, Request $request): void
    {
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
    }

    /**
     * Закрыть сессию: выйти из гварда и уничтожить данные сессии.
     */
    public function end(Request $request): void
    {
        Auth::guard('web')->logout();

        // invalidate() чистит данные, regenerateToken() — CSRF-токен:
        // старый cookie после выхода бесполезен.
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
