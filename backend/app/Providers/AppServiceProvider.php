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

        // Регистрация — отдельное окно счёта. Общий лимитер входа иначе делил бы
        // попытки с входом: после серии неудачных логинов с того же адреса
        // (или из-за одного адреса за прокси) регистрация отвечала бы 429.
        // Лимит щедрее: регистрация — редкое действие, и она не подбирает пароль.
        RateLimiter::for('auth-register', fn (Request $request): Limit => Limit::perMinute(5)->by((string) $request->ip()));
    }
}
