<?php

namespace App\Modules\User\Infrastructure\Providers;

use App\Modules\User\Domain\Contracts\UserMetaRepository;
use App\Modules\User\Domain\Contracts\UserRepository;
use App\Modules\User\Infrastructure\Persistence\EloquentUserMetaRepository;
use App\Modules\User\Infrastructure\Persistence\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Привязка портов модуля User к инфраструктурным адаптерам.
 *
 * UserRegistrar зависит только от порта и разрешается автовайрингом.
 */
final class UserServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов модуля.
     */
    public function register(): void
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(UserMetaRepository::class, EloquentUserMetaRepository::class);
    }

    /**
     * Запуск сервисов модуля.
     */
    public function boot(): void
    {
        //
    }
}
