<?php

use App\Modules\User\Infrastructure\Http\Controllers\RegisterController;
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
    Route::post('user/register', RegisterController::class)->name('api.v1.user.register');
});
