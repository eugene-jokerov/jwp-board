<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов приложения.
     */
    public function register(): void
    {
        //
    }

    /**
     * Запуск сервисов приложения.
     */
    public function boot(): void
    {
        // Headless API без сессий: гварды Laravel восстанавливаются из состояния
        // запроса (в тестах — из сессии), а вход выполняется только по Bearer-токену
        // Sanctum. Сбрасываем закешированные гварды, чтобы запрос не получал
        // пользователя из сессии в обход токена.
        Auth::forgetGuards();
    }
}
