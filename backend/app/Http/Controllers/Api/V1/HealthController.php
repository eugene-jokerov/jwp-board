<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Служебный эндпоинт: проверка доступности API.
 * Используется в smoke-тестах, healthcheck'ах и при настройке мобильного клиента.
 *
 * Окружение (APP_ENV) не раскрывается: эндпоинт публичный, а окружение —
 * часть внутренней конфигурации.
 */
final class HealthController extends Controller
{
    /**
     * Вернуть текущий статус сервиса.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => 'ok',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
