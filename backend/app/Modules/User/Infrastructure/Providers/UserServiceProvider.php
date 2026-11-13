<?php

namespace App\Modules\User\Infrastructure\Providers;

use App\Modules\User\Domain\Contracts\PasswordVerifier;
use App\Modules\User\Domain\Contracts\TokenIssuer;
use App\Modules\User\Domain\Contracts\UserMetaRepository;
use App\Modules\User\Domain\Contracts\UserRepository;
use App\Modules\User\Infrastructure\Persistence\EloquentPasswordVerifier;
use App\Modules\User\Infrastructure\Persistence\EloquentUserMetaRepository;
use App\Modules\User\Infrastructure\Persistence\EloquentUserRepository;
use App\Modules\User\Infrastructure\Sanctum\SanctumTokenIssuer;
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
        $this->app->bind(PasswordVerifier::class, EloquentPasswordVerifier::class);
        $this->app->bind(TokenIssuer::class, SanctumTokenIssuer::class);
    }

    /**
     * Запуск сервисов модуля.
     */
    public function boot(): void
    {
        //
    }
}
