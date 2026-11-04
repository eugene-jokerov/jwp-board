<?php

/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
| API используется мобильным клиентом (React Native не присылает Origin),
| а также веб-клиентом (Expo Web) с аутентификацией по сессионной cookie.
|
| allowed_methods нельзя оставлять пустым: SanitizeMiddleware превращает
| '*' в ['*'], а этого не понимает Symfony CORS. Указываем явно.
*/

return [
    'paths' => ['api/*', 'up'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
    | Веб-клиент шлёт cookie сессии, поэтому origin должен быть конкретным:
    | браузер отклоняет запросы с credentials при 'allowed_origins' => ['*'].
    */
    'allowed_origins' => explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*')),

    'allowed_origins_patterns' => [],

    // X-XSRF-TOKEN обязателен: Sanctum проверяет его на stateful-запросах.
    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With', 'X-XSRF-TOKEN'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
