<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Лимитер для входа и регистрации: перебор паролей не должен
        // бесплатно нагружать БД и выдачу токенов. Счётчик — по IP клиента,
        // маршруты помечены middleware `throttle:auth`.
        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->ip()));
    }
}
