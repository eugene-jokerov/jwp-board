<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

return [
    /*
    | Домены, на которых Sanctum ставит cookie (SPA-режим).
    | Мобильный клиент использует Bearer-токены, поэтому cookie не нужны.
    */
    'stateful' => explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
    ))),

    /*
    | Гвард для Sanctum. В проекте нет SPA на сессиях: аутентификация идёт
    | только по Bearer-токену, поэтому сессионные гварды не подключаются.
    */
    'guard' => [],

    /*
    | Время жизни токена в минутах. null — токен живёт до явного отзыва (logout).
    */
    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
