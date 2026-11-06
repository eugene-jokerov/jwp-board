<?php

use App\Modules\Direction\Domain\Exceptions\DirectionHasCards;
use App\Modules\Direction\Domain\Exceptions\DirectionNotFound;
use App\Modules\Task\Domain\Exceptions\ColumnChangeBlocked;
use App\Modules\Task\Domain\Exceptions\InvalidHierarchy;
use App\Modules\Task\Domain\Exceptions\TaskHasChildren;
use App\Modules\Task\Domain\Exceptions\TaskNotFound;
use App\Modules\User\Domain\Exceptions\EmailAlreadyRegistered;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/*
|--------------------------------------------------------------------------
| Bootstrap приложения (headless API + публичный сайт)
|--------------------------------------------------------------------------
| Префикс /api для routes/api.php добавляется фреймворком автоматически.
| Веб-роутинг существует только для публичного сайта: routes/web.php отдаёт
| Blade-шаблоны лендинга и юридических страниц (resources/views). Веб-версия
| приложения (/board/) — статика и до Laravel не доходит. Blade-провайдер
| подключён в bootstrap/providers.php (Illuminate\View\ViewServiceProvider).
*/
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: [
            __DIR__.'/../routes/api.php',
            // Маршруты модулей подключаются здесь: префикс /api и middleware группирует фреймворк.
            __DIR__.'/../app/Modules/User/routes/api.php',
            __DIR__.'/../app/Modules/Direction/routes/api.php',
            __DIR__.'/../app/Modules/Task/routes/api.php',
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
        // Остальные маршруты (лендинг) ведут себя как обычный сайт: редирект
        // браузера не должен превращаться в JSON-ответ только потому, что
        // клиент прислал Accept: application/json.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*')
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

        // Не найдено: чужой ресурс неотличим от несуществующего.
        $exceptions->render(fn (DirectionNotFound|TaskNotFound $e) => response()->json([
            'message' => $e->getMessage(),
        ], Response::HTTP_NOT_FOUND));

        // Конфликт состояния: запрет удаления и запрет смены колонки.
        $exceptions->render(fn (DirectionHasCards|TaskHasChildren|ColumnChangeBlocked $e) => response()->json([
            'message' => $e->getMessage(),
        ], Response::HTTP_CONFLICT));

        // Некорректная иерархия карточки — это ошибка входных данных.
        $exceptions->render(fn (InvalidHierarchy $e) => response()->json([
            'message' => $e->getMessage(),
            'errors' => [
                'hierarchy' => [$e->getMessage()],
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    })->create();
