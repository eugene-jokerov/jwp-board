<?php

use App\Http\Middleware\DenyReadOnlyWrites;
use App\Modules\Direction\Domain\Exceptions\DirectionHasCards;
use App\Modules\Direction\Domain\Exceptions\DirectionLimitReached;
use App\Modules\Direction\Domain\Exceptions\DirectionNotFound;
use App\Modules\Task\Domain\Exceptions\ColumnChangeBlocked;
use App\Modules\Task\Domain\Exceptions\InvalidHierarchy;
use App\Modules\Task\Domain\Exceptions\TaskHasChildren;
use App\Modules\Task\Domain\Exceptions\TaskNotFound;
use App\Modules\User\Domain\Exceptions\EmailAlreadyRegistered;
use App\Modules\User\Domain\Exceptions\ReadOnlyMode;
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
        // Гостя не уводим на именованный маршрут `login`: веб-входа в приложении
        // нет (аутентификация — /api/v1/auth/*), и Laravel по умолчанию падал бы
        // с RouteNotFoundException. Вместо редиректа гость получает 401, а
        // маршруты админки переводят отказ в 404 (App\Http\Middleware\HideAdminPanel).
        $middleware->redirectGuestsTo(fn (): ?string => null);

        // Allowed headers должны обрабатываться до CSRF, поэтому CORS идёт первым.
        $middleware->api(prepend: [
            HandleCors::class,
        ]);

        // Режим «только чтение» — последним в группе: аутентификация к этому
        // моменту уже выполнена, и флаг пользователя известен. Один хук
        // закрывает все изменяющие маршруты /api/v1, включая будущие.
        $middleware->api(append: [
            DenyReadOnlyWrites::class,
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

        // Достигнут лимит направлений — ошибка входных данных, а не конфликт
        // состояния: создать ещё одно направление нельзя в принципе, пока не
        // удалено одно из существующих. Ключ `direction` — чтобы клиент показал
        // текст рядом с формой создания направления.
        $exceptions->render(fn (DirectionLimitReached $e) => response()->json([
            'message' => $e->getMessage(),
            'errors' => [
                'direction' => [$e->getMessage()],
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY));

        // Режим «только чтение»: запись запрещена самой учётной записью, а не
        // состоянием данных. Ключ `account` — чтобы клиент вывел текст рядом с
        // действием, а не потерял его в общем баннере.
        $exceptions->render(fn (ReadOnlyMode $e) => response()->json([
            'message' => $e->getMessage(),
            'errors' => [
                'account' => [$e->getMessage()],
            ],
        ], Response::HTTP_FORBIDDEN));
    })->create();
