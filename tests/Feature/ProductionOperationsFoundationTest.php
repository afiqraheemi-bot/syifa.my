<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class ProductionOperationsFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('operations.runtime_checks.enabled', false);
    }

    public function test_health_endpoint_returns_lightweight_json_contract(): void
    {
        $this->getJson('/operations/health')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'status' => 'ok',
                'type' => 'health',
                'detail' => 'Application health endpoint is available.',
                'checks' => [],
            ]);
    }

    public function test_ready_endpoint_returns_lightweight_json_contract(): void
    {
        $this->getJson('/operations/ready')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ready',
                'type' => 'readiness',
                'detail' => 'Application is ready to receive traffic.',
                'checks' => [
                    'infrastructure' => [
                        'status' => 'ready',
                        'capabilities' => [
                            [
                                'capability' => 'cache',
                                'status' => 'ready',
                                'required' => true,
                            ],
                            [
                                'capability' => 'queue',
                                'status' => 'ready',
                                'required' => true,
                            ],
                            [
                                'capability' => 'session',
                                'status' => 'ready',
                                'required' => true,
                            ],
                            [
                                'capability' => 'logging',
                                'status' => 'ready',
                                'required' => true,
                            ],
                            [
                                'capability' => 'filesystem',
                                'status' => 'ready',
                                'required' => true,
                            ],
                        ],
                    ],
                    'dependencies' => [
                        'status' => 'ready',
                        'dependencies' => [],
                    ],
                ],
            ]);
    }

    public function test_live_endpoint_returns_lightweight_json_contract(): void
    {
        $this->getJson('/operations/live')
            ->assertOk()
            ->assertExactJson([
                'status' => 'alive',
                'type' => 'liveness',
                'detail' => 'Application process is alive.',
                'checks' => [],
            ]);
    }

    public function test_application_info_endpoint_returns_safe_metadata_only(): void
    {
        $response = $this->getJson('/operations/info')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'application' => [
                    'service' => 'syifa.my',
                    'component' => 'modular-monolith',
                    'api_version' => 'v1',
                ],
            ]);

        $content = $response->getContent();

        self::assertIsString($content);

        foreach ([
            'APP_KEY',
            'APP_ENV',
            'APP_DEBUG',
            'DB_',
            'REDIS_',
            'password',
            'secret',
            'token',
            'laravel',
            'framework',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $content);
        }
    }

    public function test_canonical_health_routes_preserve_liveness_and_readiness_semantics(): void
    {
        $this->getJson('/health/live')
            ->assertOk()
            ->assertJsonPath('status', 'alive')
            ->assertJsonPath('checks', []);

        $this->getJson('/health/ready')
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonStructure([
                'checks' => [
                    'infrastructure',
                    'dependencies',
                ],
            ]);
    }

    public function test_release_endpoints_expose_only_approved_build_metadata(): void
    {
        Config::set('operations.release', [
            'version' => '1.0.0',
            'build_id' => 'build-20260724-1',
            'commit' => '0123456789abcdef',
            'built_at' => '2026-07-24T10:00:00Z',
        ]);

        $this->getJson('/version')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'version' => '1.0.0',
            ]);

        $this->getJson('/build')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'build' => [
                    'build_id' => 'build-20260724-1',
                    'commit' => '0123456789abcdef',
                    'built_at' => '2026-07-24T10:00:00Z',
                ],
            ]);

        $response = $this->getJson('/release')
            ->assertOk()
            ->assertJsonPath('release.version', '1.0.0')
            ->assertJsonPath('release.build.commit', '0123456789abcdef');

        $content = $response->getContent();

        self::assertIsString($content);

        foreach (['APP_KEY', 'DB_PASSWORD', 'REDIS_PASSWORD', 'secret', 'token'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $content);
        }
    }

    public function test_operations_configuration_is_centralized_and_customizable(): void
    {
        Config::set('operations.application.service', 'syifa-safe-test');
        Config::set('operations.application.component', 'operations-foundation');
        Config::set('operations.application.api_version', 'v1-test');

        $this->getJson('/operations/info')
            ->assertOk()
            ->assertJsonPath('application.service', 'syifa-safe-test')
            ->assertJsonPath('application.component', 'operations-foundation')
            ->assertJsonPath('application.api_version', 'v1-test');
    }
}
