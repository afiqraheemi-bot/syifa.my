<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class InfrastructureReadinessReportingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('operations.runtime_checks.enabled', false);
    }

    public function test_readiness_endpoint_reports_safe_infrastructure_status(): void
    {
        $response = $this->getJson('/operations/ready')
            ->assertOk()
            ->assertJsonPath('checks.infrastructure.status', 'ready')
            ->assertJsonPath('checks.infrastructure.capabilities.0.capability', 'cache')
            ->assertJsonPath('checks.infrastructure.capabilities.0.status', 'ready');

        $content = $response->getContent();

        self::assertIsString($content);

        foreach ([
            'APP_KEY',
            'DB_PASSWORD',
            'REDIS_PASSWORD',
            'AWS_SECRET',
            'sensitive-provider-value',
            'cache.default',
            'queue.default',
            'session.driver',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $content);
        }
    }

    public function test_readiness_endpoint_fails_safely_when_required_capability_is_not_configured(): void
    {
        Config::set('cache.default', '');

        $this->getJson('/operations/ready')
            ->assertStatus(503)
            ->assertJsonPath('status', 'not_ready')
            ->assertJsonPath('checks.infrastructure.status', 'not_ready')
            ->assertJsonPath('checks.infrastructure.capabilities.0.capability', 'cache')
            ->assertJsonPath('checks.infrastructure.capabilities.0.reason_code', 'missing_default');
    }
}
