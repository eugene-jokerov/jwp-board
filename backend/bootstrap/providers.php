<?php

use App\Modules\Direction\Infrastructure\Providers\DirectionServiceProvider;
use App\Modules\History\Infrastructure\Providers\HistoryServiceProvider;
use App\Modules\Task\Infrastructure\Providers\TaskServiceProvider;
use App\Modules\User\Infrastructure\Providers\UserServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Shared\Infrastructure\Providers\BusServiceProvider;
use Illuminate\View\ViewServiceProvider;

/*
| Провайдеры приложения (Laravel 13 не подключает их автоматически).
| ViewServiceProvider нужен публичному сайту: лендинг и юридические страницы —
| Blade-шаблоны в resources/views. API от шаблонов не зависит.
| AdminPanelProvider — админ-панель Filament (/admin), добавлен filament:install.
*/
return [
    DirectionServiceProvider::class,
    HistoryServiceProvider::class,
    TaskServiceProvider::class,
    UserServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
    BusServiceProvider::class,
    ViewServiceProvider::class,
];
