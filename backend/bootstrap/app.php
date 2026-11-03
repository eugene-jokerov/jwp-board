<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/*
|--------------------------------------------------------------------------
| Bootstrap приложения (headless API)
|--------------------------------------------------------------------------
| Регистрируем только API-маршруты: web-роутинга и Blade-шаблонов в проекте нет.
| Префикс /api для routes/api.php добавляется фреймворком автоматически.
*/
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Все запросы — через API-группу: без сессий и CSRF, с троттлингом.
        $middleware->api(prepend: [
            Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API всегда отвечает JSON (в том числе на 404/405/500).
        $exceptions->shouldRenderJsonWhen(
            fn (Illuminate\Http\Request $request): bool => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
