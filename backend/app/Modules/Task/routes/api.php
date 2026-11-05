<?php

use App\Modules\Task\Infrastructure\Http\Controllers\DestroyTaskController;
use App\Modules\Task\Infrastructure\Http\Controllers\IndexTaskController;
use App\Modules\Task\Infrastructure\Http\Controllers\ShowTaskController;
use App\Modules\Task\Infrastructure\Http\Controllers\StoreTaskController;
use App\Modules\Task\Infrastructure\Http\Controllers\UpdateTaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Маршруты модуля Task
|--------------------------------------------------------------------------
| Карточки канбан-доски: инициативы, проекты и задачи в одной таблице.
| Смена колонки — это PATCH /tasks/{task} с полем column: отдельного
| эндпоинта перехода нет, поэтому правила перехода проверяются в том же
| сценарии, что и остальные правки карточки.
*/

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('tasks', IndexTaskController::class)->name('api.v1.tasks.index');
    Route::post('tasks', StoreTaskController::class)->name('api.v1.tasks.store');
    Route::get('tasks/{task}', ShowTaskController::class)
        ->whereNumber('task')
        ->name('api.v1.tasks.show');
    Route::patch('tasks/{task}', UpdateTaskController::class)
        ->whereNumber('task')
        ->name('api.v1.tasks.update');
    Route::delete('tasks/{task}', DestroyTaskController::class)
        ->whereNumber('task')
        ->name('api.v1.tasks.destroy');
});
