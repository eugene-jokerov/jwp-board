<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Web\AuthController as WebAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API-маршруты (headless API)
|--------------------------------------------------------------------------
| Весь публичный контракт мобильного приложения живёт здесь.
| Префикс /api добавляется автоматически (bootstrap/app.php), версия — через group.
|
| Аутентификация:
|  - мобильный клиент: Bearer-токены Sanctum (/auth/*, /user/register);
|  - веб-клиент: сессионная cookie (/auth/web/*).
| Остальные защищённые маршруты общие: auth:sanctum принимает оба канала.
*/

Route::prefix('v1')->group(function (): void {
    // Служебные маршруты: доступны без аутентификации.
    Route::get('health', HealthController::class)->name('api.v1.health');

    // Аутентификация по токенам Sanctum (мобильный клиент).
    // throttle:auth — защита от перебора пароля (лимитер в AppServiceProvider).
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth')
        ->name('api.v1.auth.login');

    // Аутентификация веб-клиента: сессия в HttpOnly-cookie вместо токена.
    // Регистрация веба идёт отдельным лимитером, как и мобильная: у входа свой
    // счётчик попыток (перебор пароля), у регистрации — свой.
    Route::prefix('auth/web')->group(function (): void {
        Route::post('login', [WebAuthController::class, 'login'])
            ->middleware('throttle:auth')
            ->name('api.v1.auth.web.login');
        Route::post('register', [WebAuthController::class, 'register'])
            ->middleware('throttle:auth-register')
            ->name('api.v1.auth.web.register');
    });

    // Защищённые маршруты: shared для токена и сессии.
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');

        // Веб: восстановление сессии после перезагрузки и выход.
        Route::get('auth/web/me', [WebAuthController::class, 'me'])->name('api.v1.auth.web.me');
        Route::post('auth/web/logout', [WebAuthController::class, 'logout'])->name('api.v1.auth.web.logout');
    });
});
