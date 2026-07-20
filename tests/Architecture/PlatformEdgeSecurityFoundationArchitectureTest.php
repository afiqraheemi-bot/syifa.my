<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class PlatformEdgeSecurityFoundationArchitectureTest extends TestCase
{
    public function test_edge_security_is_registered_once_as_global_middleware(): void
    {
        $bootstrap = $this->source('bootstrap/app.php');

        self::assertStringContainsString('ApplyPlatformEdgeSecurity::class', $bootstrap);
        self::assertSame(1, substr_count($bootstrap, 'ApplyPlatformEdgeSecurity::class'));
        self::assertStringContainsString('ApplyHttpSecurityHeaders::class', $bootstrap);
    }

    public function test_edge_security_configuration_is_centralized(): void
    {
        $config = $this->source('config/edge_security.php');

        foreach ([
            'trusted_proxies',
            'trusted_hosts',
            'secure_url_generation',
            'EDGE_TRUSTED_PROXIES',
            'EDGE_TRUSTED_HOSTS',
        ] as $expected) {
            self::assertStringContainsString($expected, $config);
        }
    }

    public function test_edge_security_has_no_business_layer_dependency(): void
    {
        $middleware = $this->source('app/Http/Middleware/ApplyPlatformEdgeSecurity.php');

        foreach ([
            'App\\Modules\\',
            'Domain\\',
            'Application\\',
            'Authorization\\',
            'Authentication\\',
            'Controller',
            'Route::',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $middleware);
        }
    }

    public function test_edge_security_does_not_hardcode_infrastructure_provider_ranges(): void
    {
        $combined = $this->source('config/edge_security.php')
            .$this->source('app/Http/Middleware/ApplyPlatformEdgeSecurity.php');

        foreach ([
            'cloudflare',
            'Cloudflare',
            'amazonaws',
            'AWS ALB',
            '173.245.',
            '103.21.',
            '103.22.',
            '103.31.',
            '2400:cb00',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $combined);
        }

        self::assertStringNotContainsString("'*'", $this->source('config/edge_security.php'));
        self::assertStringNotContainsString('"*"', $this->source('config/edge_security.php'));
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);

        self::assertIsString($source);

        return $source;
    }
}
