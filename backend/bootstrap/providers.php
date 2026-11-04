<?php

use App\Modules\User\Infrastructure\Providers\UserServiceProvider;
use App\Providers\AppServiceProvider;
use App\Shared\Infrastructure\Providers\BusServiceProvider;

return [
    UserServiceProvider::class,
    BusServiceProvider::class,
    AppServiceProvider::class,
];
