<?php

namespace App\Modules\User\Infrastructure\Providers;

use App\Modules\User\Domain\Contracts\UserRepository;
use App\Modules\User\Domain\Services\UserRegistrar;
use App\Modules\User\Infrastructure\Persistence\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Привязка портов модуля User к инфраструктурным адаптерам.
 */
final class UserServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов модуля.
     */
    public function register(): void
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
    }

    /**
     * Запуск сервисов модуля.
     */
    public function boot(): void
    {
        // UserRegistrar не требует конфигурации: зависимости приходят через DI.
        $this->app->bind(UserRegistrar::class);
    }
}
