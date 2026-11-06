<?php

namespace Tests\Feature;

use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * Публичный сайт: лендинг живёт в Laravel (/), веб-версия приложения — статика
 * /board/ (её отдаёт веб-сервер: nginx локально, .htaccess на хостинге,
 * поэтому запросы /board/** в PHP-тестах не проверяются).
 */
final class LandingTest extends TestCase
{
    public function test_root_serves_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        self::assertStringContainsString('text/html', (string) $response->headers->get('content-type'));

        // Ответ отдаётся потоком (response()->file), поэтому содержимое
        // проверяем по самому файлу лендинга.
        $html = (string) file_get_contents(resource_path('views/landing.html'));

        self::assertStringContainsString('JWP Board', $html);
        self::assertStringContainsString('href="/board/"', $html);
    }

    public function test_landing_has_no_duplicate_url(): void
    {
        // Разметка лендинга живёт вне веб-корня: прямых адресов к файлу нет,
        // иначе одна и та же страница открывалась бы по нескольким URL.
        self::assertFileDoesNotExist(public_path('landing'));
        self::assertFileExists(resource_path('views/landing.html'));

        $this->get('/landing/')->assertStatus(302);
        $this->get('/landing/index.html')->assertStatus(302);
    }

    public function test_board_without_trailing_slash_redirects(): void
    {
        $response = $this->get('/board');

        $response->assertStatus(302);
        self::assertSame('/board/', $response->headers->get('Location'));
    }

    public function test_unknown_page_redirects_to_landing(): void
    {
        $response = $this->get('/auth');

        $response->assertStatus(302);
        self::assertSame('/', $response->headers->get('Location'));
    }

    public function test_unknown_api_route_still_returns_json_404(): void
    {
        $response = $this->getJson('/api/v1/unknown-endpoint');

        $response->assertNotFound();
        self::assertStringContainsString('application/json', (string) $response->headers->get('content-type'));
    }

    public function test_unknown_non_get_page_returns_404(): void
    {
        // Лендинг на POST не редиректим: это не страница, а неизвестный маршрут.
        // 405 для GET-маршрута Laravel считает тем же «не найдено» — и статус,
        // и JSON-тело не должны подменяться редиректом на лендинг.
        $this->post('/auth')->assertStatus(Response::HTTP_METHOD_NOT_ALLOWED);
    }
}
