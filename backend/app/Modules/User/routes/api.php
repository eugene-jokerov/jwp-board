<?php

use App\Modules\User\Infrastructure\Http\Controllers\DeleteUserSettingsController;
use App\Modules\User\Infrastructure\Http\Controllers\IndexUserSettingsController;
use App\Modules\User\Infrastructure\Http\Controllers\RegisterController;
use App\Modules\User\Infrastructure\Http\Controllers\UpdateUserSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Маршруты модуля User
|--------------------------------------------------------------------------
| Файл подключается в bootstrap/app.php рядом с routes/api.php: префикс /api
| и группа middleware `api` добавляются фреймворком автоматически.
| Версия API — внутри group, чтобы путь оставался /api/v1/user/*.
*/

Route::prefix('v1')->group(function (): void {
    // Регистрация доступна без аутентификации: возвращает Bearer-токен.
    // throttle:auth — лимитер из AppServiceProvider (защита от массовых регистраций).
    // throttle:auth-register — отдельное окно: общие 10 попыток в минуту на входе
    // делят с регистрацией и другие адреса, и после нескольких неудачных входов
    // соседний пользователь не смог бы зарегистрироваться.
    Route::post('user/register', RegisterController::class)
        ->middleware('throttle:auth-register')
        ->name('api.v1.user.register');

    // Пользовательские настройки (свёрнутые уровни и колонки доски).
    // Один ключ — одно значение: сохранение идёт UPSERT-ом, поэтому клиенту
    // не нужно знать, была ли настройка сохранена раньше.
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('user/settings', IndexUserSettingsController::class)
            ->name('api.v1.user.settings.index');
        Route::put('user/settings', UpdateUserSettingsController::class)
            ->name('api.v1.user.settings.update');
        Route::delete('user/settings/{key}', DeleteUserSettingsController::class)
            ->where('key', '[a-z0-9_.]+')
            ->name('api.v1.user.settings.destroy');
    });
});
