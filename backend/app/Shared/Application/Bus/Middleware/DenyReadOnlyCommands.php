<?php

namespace App\Shared\Application\Bus\Middleware;

use App\Modules\User\Domain\Exceptions\ReadOnlyMode;
use App\Modules\User\Domain\User;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Пайп шины команд: запрещает запись пользователю в режиме «только чтение».
 *
 * HTTP-запросы уже отсекает DenyReadOnlyWrites, поэтому здесь проверка
 * опирается на аутентификацию Laravel (`Auth::user()`), а не на Request.
 * Вне HTTP пользователя надо явно войти в гвард `web` до dispatch — иначе
 * Auth::user() вернёт null и правило не применится. Для консоли это делает
 * команда `tinker-as` (routes/console.php, `make artisan-as who=42`).
 *
 * Пайп работает без условия: команды админки (*ForAdminCommand) получают
 * отдельный набор пайпов в BusServiceProvider — администратор правит доски
 * read-only пользователей из /admin, и его собственный флаг здесь не при чём.
 */
final readonly class DenyReadOnlyCommands
{
    /**
     * Выполнить следующий обработчик, если учётная запись не read-only.
     *
     * @param  Closure(object): mixed  $next
     *
     * @throws ReadOnlyMode если текущий пользователь работает в режиме только для чтения
     */
    public function __invoke(object $command, Closure $next): mixed
    {
        $user = Auth::user();

        // Гость (или контекст без аутентификации) — не забота этого пайпа:
        // сценарии, которым нужен владелец, проверяют его сами.
        if ($user instanceof User && $user->isReadonly()) {
            throw ReadOnlyMode::denied();
        }

        return $next($command);
    }
}
