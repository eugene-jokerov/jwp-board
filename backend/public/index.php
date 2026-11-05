<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

/*
| Точка входа API: все запросы проксируются в Laravel.
*/
define('LARAVEL_START', microtime(true));

// Режим обслуживания (php artisan down).
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
