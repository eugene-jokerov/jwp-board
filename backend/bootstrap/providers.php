<?php

use App\Modules\Direction\Infrastructure\Providers\DirectionServiceProvider;
use App\Modules\History\Infrastructure\Providers\HistoryServiceProvider;
use App\Modules\Task\Infrastructure\Providers\TaskServiceProvider;
use App\Modules\User\Infrastructure\Providers\UserServiceProvider;
use App\Providers\AppServiceProvider;
use App\Shared\Infrastructure\Providers\BusServiceProvider;

return [
    UserServiceProvider::class,
    DirectionServiceProvider::class,
    TaskServiceProvider::class,
    HistoryServiceProvider::class,
    BusServiceProvider::class,
    AppServiceProvider::class,
];
