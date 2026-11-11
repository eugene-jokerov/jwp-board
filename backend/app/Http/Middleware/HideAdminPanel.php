<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Скрывает админ-панель от всех, кому она недоступна.
 *
 * Панель не афишируется: гость и авторизованный пользователь без прав
 * администратора получают 404 — тот же ответ, что на несуществующий адрес.
 * Так посетитель не узнаёт ни о существовании панели, ни о том, что вход
 * возможен: Filament сам ответил бы 302 на страницу входа, а после неё —
 * 403, и оба ответа подтверждали бы наличие админки.
 *
 * Права проверяет домен, а не UI-слой: `User::canAccessPanel()` — источник
 * правды (флаг is_admin), middleware лишь переводит отказ в HTTP-ответ.
 *
 * Middleware регистрируется сразу после старта сессии — до `AuthenticateSession`
 * и `Authenticate` (см. AdminPanelProvider), поэтому гость получает 404 ещё до
 * того, как Filament успеет бросить редирект на вход или 403.
 */
class HideAdminPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        abort_if($user === null, Response::HTTP_NOT_FOUND);

        // Повторяем условие Filament\Http\Middleware\Authenticate: у панели нет
        // собственного метода проверки доступа, решение принимает модель. Если
        // модель контракт FilamentUser не реализует, доступа нет никому —
        // послабление фреймворка для APP_ENV=local здесь намеренно не действует.
        abort_unless(
            $user instanceof FilamentUser
                && $user->canAccessPanel(Filament::getCurrentOrDefaultPanel()),
            Response::HTTP_NOT_FOUND,
        );

        return $next($request);
    }
}
