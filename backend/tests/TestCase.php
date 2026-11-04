<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Выполнить HTTP-запрос к приложению.
     *
     * `auth:sanctum` переключает резолвер пользователя на гвард `sanctum`
     * (AuthManager::shouldUse()), а тот — RequestGuard — кеширует пользователя
     * и запрос первого вызова. В реальном окружении процесс живёт один запрос,
     * а PHPUnit — один процесс на весь тест, поэтому перед каждым запросом
     * сбрасываем закешированные гварды: иначе следующий запрос мог получить
     * пользователя предыдущего (например, остаться авторизованным после logout).
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $cookies
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $server
     * @return TestResponse<Response>
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        Auth::forgetGuards();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
