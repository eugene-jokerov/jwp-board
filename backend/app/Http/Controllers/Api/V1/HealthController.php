<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Служебный эндпоинт: проверка доступности API.
 * Используется в smoke-тестах, healthcheck'ах и при настройке мобильного клиента.
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
                'environment' => app()->environment(),
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
