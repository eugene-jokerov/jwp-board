<?php

namespace App\Http\Middleware;

use App\Modules\User\Domain\Exceptions\ReadOnlyMode;
use App\Modules\User\Domain\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Запрещает изменяющие запросы пользователю в режиме «только чтение».
 *
 * Правило одно для всего API: если у пользователя флаг `readonly`, ему доступны
 * лишь методы чтения. Проверка живёт на пути запроса, а не в контроллерах:
 * middleware накрывает и существующие маршруты, и те, что появятся позже, —
 * новую мутацию невозможно «забыть» закрыть.
 *
 * Послабления:
 *  - методы чтения (GET/HEAD/OPTIONS) проходят всегда;
 *  - выход из аккаунта разрешён: пользователь должен иметь возможность закрыть
 *    сессию, а данные при этом не меняются;
 *  - гость пропускается молча — ответ 401 отдаёт `auth:sanctum`, и это не дело
 *    этого middleware.
 *
 * Middleware регистрируется в конце группы `api` (bootstrap/app.php), поэтому
 * выполняется после аутентификации: `$request->user()` уже определён.
 */
class DenyReadOnlyWrites
{
    /**
     * Методы, которые ничего не меняют, — их пропускаем без проверки.
     *
     * @var array<int, string>
     */
    private const array READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * Единственные изменяющие запросы, доступные в режиме «только чтение».
     *
     * Пути полные: `Request::is()` сравнивает их с URI запроса, а префикс `api`
     * уже входит в адрес. Логика одна для мобильного и веб-канала: выход токен
     * не меняет, гасится только сессия.
     *
     * @var array<int, string>
     */
    private const array ALLOWED_WRITE_PATHS = [
        'api/v1/auth/logout',
        'api/v1/auth/web/logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), self::READ_METHODS, true)) {
            return $next($request);
        }

        if ($request->is(...self::ALLOWED_WRITE_PATHS)) {
            return $next($request);
        }

        $user = $request->user();

        // Гость: запись не «запрещена пользователю», её просто не от кого
        // выполнять — 401 вернёт middleware аутентификации.
        if ($user instanceof User && $user->isReadonly()) {
            throw ReadOnlyMode::denied();
        }

        return $next($request);
    }
}
