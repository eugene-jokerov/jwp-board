<?php

use App\Modules\User\Infrastructure\Persistence\EloquentUserModel;

return [
    /*
    | Аутентификация. Гвард web используется Sanctum для SPA-режима;
    | мобильный клиент ходит по токенам (Bearer).
    */
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            // Eloquent-адаптер модуля User: он же реализует контракты
            // админки (FilamentUser), по которым /admin проверяет доступ.
            'model' => EloquentUserModel::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
