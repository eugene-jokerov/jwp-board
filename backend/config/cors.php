<?php

/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
| API публичный и используется мобильным клиентом (React Native не присылает
| Origin), а также Expo Web и отладчиком в браузере.
| allowed_methods нельзя оставлять пустым: SanitizeMiddleware превращает
| '*' в ['*'], а этого не понимает Symfony CORS. Указываем явно.
*/

return [
    'paths' => ['api/*', 'up'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With', 'X-XSRF-TOKEN'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
