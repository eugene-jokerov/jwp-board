<?php

use App\Modules\Direction\Infrastructure\Providers\DirectionBusRegistry;
use App\Modules\Task\Infrastructure\Providers\TaskBusRegistry;
use App\Modules\User\Infrastructure\Providers\UserBusRegistry;

/*
 * Модули, регистрирующие сценарии в шине.
 *
 * BusServiceProvider опрашивает каждый класс по порту BusRegistry: карта
 * «команда/запрос → хендлер» объявлена в модуле, а не в общей инфраструктуре.
 * Сюда добавляется модуль, у которого появились команды или запросы.
 */
return [
    'registries' => [
        DirectionBusRegistry::class,
        TaskBusRegistry::class,
        UserBusRegistry::class,
    ],
];
