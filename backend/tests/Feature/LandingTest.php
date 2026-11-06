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

        $html = (string) $response->getContent();

        self::assertStringContainsString('JWP Board', $html);
        self::assertStringContainsString('href="/board/"', $html);
        self::assertStringContainsString('Посмотреть демо', $html);
        self::assertStringContainsString('src="/images/board.png"', $html);
    }

    public function test_landing_substitutes_year(): void
    {
        // Год копирайта вычисляется шаблоном (now()), а не правится руками:
        // в готовой странице не должно остаться незаполненных конструкций.
        $html = (string) $this->get('/')->getContent();

        self::assertStringContainsString('© '.now()->format('Y').' JWP Board', $html);
    }

    public function test_views_are_rendered_by_blade(): void
    {
        // Публичный сайт отдаётся Blade-шаблонами: директивы и компоненты
        // должны быть скомпилированы, а не попасть в ответ как текст.
        $html = (string) $this->get('/privacy')->getContent();

        foreach (['@props', '@php', 'x-legal.layout', '{{', '{!!'] as $marker) {
            self::assertStringNotContainsString($marker, $html, "В ответе остался Blade-маркер {$marker}");
        }
    }

    public function test_landing_links_to_legal_pages(): void
    {
        $html = (string) $this->get('/')->getContent();

        foreach (['/privacy', '/terms', '/cookies'] as $path) {
            self::assertStringContainsString('href="'.$path.'"', $html);
        }
    }

    public function test_legal_pages_are_available(): void
    {
        foreach (['/privacy', '/terms', '/cookies'] as $path) {
            $response = $this->get($path);

            $response->assertOk();
            self::assertStringContainsString(
                'text/html',
                (string) $response->headers->get('content-type'),
                "Страница {$path} должна отдаваться как HTML"
            );

            $html = (string) $response->getContent();

            // Реквизиты выводятся шаблоном из config/legal.php, а не лежат
            // в разметке: в ответе не должно остаться Blade-подстановок.
            self::assertStringNotContainsString('{{', $html, "На странице {$path} остались подстановки");
        }
    }

    public function test_legal_pages_mark_missing_operator_fields(): void
    {
        // Незаполненные реквизиты показываются пометкой, а не пустой ячейкой:
        // это защищает от публикации документа без данных оператора.
        config(['legal.operator.name' => null, 'legal.contact_email' => '']);

        $html = (string) $this->get('/privacy')->getContent();

        self::assertStringContainsString('<dd>не указан</dd>', $html);
    }

    public function test_privacy_page_uses_operator_config(): void
    {
        config([
            'legal.operator.name' => 'ООО «Тест»',
            'legal.contact_email' => 'privacy@example.test',
        ]);

        $html = (string) $this->get('/privacy')->getContent();

        self::assertStringContainsString('ООО «Тест»', $html);
        self::assertStringContainsString('privacy@example.test', $html);
        self::assertStringContainsString('152-ФЗ', $html);
    }

    public function test_legal_pages_are_not_redirected_by_fallback(): void
    {
        // Регрессия на порядок маршрутов: fallback уводит неизвестные страницы
        // на «/», и юридические адреса должны быть объявлены раньше него.
        foreach (['/privacy', '/terms', '/cookies'] as $path) {
            self::assertSame(200, $this->get($path)->getStatusCode(), "{$path} не должен редиректить");
        }
    }

    public function test_landing_has_no_duplicate_url(): void
    {
        // Шаблоны публичного сайта живут вне веб-корня: прямых адресов к файлам
        // нет, иначе одна и та же страница открывалась бы по нескольким URL.
        // Юридические страницы тоже лежат в resources/views/legal — дублей
        // вида /legal/privacy.blade.php быть не должно.
        self::assertFileDoesNotExist(public_path('landing'));
        self::assertFileDoesNotExist(public_path('legal'));
        self::assertFileExists(resource_path('views/landing.blade.php'));
        self::assertFileExists(resource_path('views/legal/privacy.blade.php'));

        $this->get('/landing/')->assertStatus(302);
        $this->get('/landing/index.html')->assertStatus(302);
        $this->get('/legal/privacy.html')->assertStatus(302);
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
