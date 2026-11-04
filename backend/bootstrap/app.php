<?php

use App\Modules\User\Domain\Exceptions\EmailAlreadyRegistered;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/*
|--------------------------------------------------------------------------
| Bootstrap приложения (headless API)
|--------------------------------------------------------------------------
| Регистрируем только API-маршруты: web-роутинга и Blade-шаблонов в проекте нет.
| Префикс /api для routes/api.php добавляется фреймворком автоматически.
*/
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: [
            __DIR__.'/../routes/api.php',
            // Маршруты модулей подключаются здесь: префикс /api и middleware группирует фреймворк.
            __DIR__.'/../app/Modules/User/routes/api.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Allowed headers должны обрабатываться до CSRF, поэтому CORS идёт первым.
        $middleware->api(prepend: [
            HandleCors::class,
        ]);

        // Два канала аутентификации:
        //  - stateful (веб-клиент): запросы с Origin/Referer из sanctum.stateful
        //    получают сессию, cookie и проверку CSRF (X-XSRF-TOKEN);
        //  - токенный (мобильный клиент): запросы без Origin идут без сессии,
        //    пользователь определяется по Bearer-токену Sanctum.
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API всегда отвечает JSON (в том числе на 404/405/500).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson()
        );

        // Доменные исключения не знают про HTTP: перевод в ответ API живёт здесь.
        $exceptions->render(function (EmailAlreadyRegistered $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [
                    'email' => [$e->getMessage()],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });
    })->create();
