<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Отключает сессионные гварды для API-запросов.
 *
 * Приложение headless: пользователь определяется только по Bearer-токену Sanctum.
 * Гварды Laravel (в том числе `web` на драйвере session) стартуют сессию и могут
 * вернуть пользователя из неё — в тестах это приводит к тому, что после logout
 * запрос продолжает проходить аутентификацию. Сбрасываем закешированные гварды
 * в начале каждого запроса.
 */
final class ForgetSessionGuards
{
    /**
     * Обработать входящий запрос.
     */
    public function handle(Request $request, Closure $next): Response
    {
        Auth::forgetGuards();

        return $next($request);
    }
}
