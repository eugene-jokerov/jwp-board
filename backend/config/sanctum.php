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
    | Гварды, которые Sanctum опрашивает до Bearer-токена.
    |
    | `web` — сессионный гвард: его использует веб-клиент (cookie-аутентификация).
    | Мобильный клиент работает по Bearer-токену: у него сессии нет, поэтому
    | гвард `web` сразу вернёт null и Sanctum перейдёт к проверке токена.
    */
    'guard' => ['web'],

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
