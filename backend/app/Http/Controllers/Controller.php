<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Базовый контроллер приложения.
 * Все контроллеры API наследуются от него (а не напрямую от BaseController).
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
}
