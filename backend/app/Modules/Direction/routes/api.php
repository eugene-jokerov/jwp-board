<?php

use App\Modules\Direction\Infrastructure\Http\Controllers\DestroyDirectionController;
use App\Modules\Direction\Infrastructure\Http\Controllers\IndexDirectionController;
use App\Modules\Direction\Infrastructure\Http\Controllers\ShowDirectionController;
use App\Modules\Direction\Infrastructure\Http\Controllers\StoreDirectionController;
use App\Modules\Direction\Infrastructure\Http\Controllers\UpdateDirectionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Маршруты модуля Direction
|--------------------------------------------------------------------------
| Файл подключается в bootstrap/app.php: префикс /api и группа middleware `api`
| добавляются фреймворком. Версия API — внутри group, чтобы путь был /api/v1/directions.
| Все маршруты закрыты auth:sanctum: направления принадлежат пользователю.
*/

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('directions', IndexDirectionController::class)->name('api.v1.directions.index');
    Route::post('directions', StoreDirectionController::class)->name('api.v1.directions.store');
    Route::get('directions/{direction}', ShowDirectionController::class)
        ->whereNumber('direction')
        ->name('api.v1.directions.show');
    Route::patch('directions/{direction}', UpdateDirectionController::class)
        ->whereNumber('direction')
        ->name('api.v1.directions.update');
    Route::delete('directions/{direction}', DestroyDirectionController::class)
        ->whereNumber('direction')
        ->name('api.v1.directions.destroy');
});
