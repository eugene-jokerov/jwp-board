<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API-маршруты (headless API)
|--------------------------------------------------------------------------
| Весь публичный контракт мобильного приложения живёт здесь.
| Префикс /api добавляется автоматически (bootstrap/app.php), версия — через group.
*/

Route::prefix('v1')->group(function (): void {
    // Служебные маршруты: доступны без аутентификации.
    Route::get('health', HealthController::class)->name('api.v1.health');

    // Аутентификация по токенам Sanctum.
    Route::post('auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

    // Защищённые маршруты.
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
    });
});
