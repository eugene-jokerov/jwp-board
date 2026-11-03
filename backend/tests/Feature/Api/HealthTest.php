<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

/**
 * Smoke-тест служебного эндпоинта API.
 */
final class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonStructure([
                'data' => ['status', 'environment', 'timestamp'],
            ]);
    }

    public function test_unknown_api_route_returns_json_404(): void
    {
        $response = $this->getJson('/api/v1/unknown-endpoint');

        $response->assertNotFound();
        self::assertStringContainsString('application/json', (string) $response->headers->get('content-type'));
    }
}
