<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class HttpSecurityFoundationArchitectureTest extends TestCase
{
    public function test_http_security_foundation_uses_one_global_middleware(): void
    {
        $bootstrap = file_get_contents(dirname(__DIR__, 2).'/bootstrap/app.php');

        self::assertIsString($bootstrap);
        self::assertStringContainsString('ApplyHttpSecurityHeaders::class', $bootstrap);
        self::assertStringContainsString('$middleware->append([', $bootstrap);
        self::assertSame(1, substr_count($bootstrap, 'ApplyHttpSecurityHeaders::class'));
    }

    public function test_http_security_foundation_does_not_touch_business_layers(): void
    {
        $middleware = file_get_contents(dirname(__DIR__, 2).'/app/Http/Middleware/ApplyHttpSecurityHeaders.php');

        self::assertIsString($middleware);

        foreach ([
            'App\\Modules\\',
            'Domain\\',
            'Application\\',
            'Infrastructure\\Persistence',
            'Controller',
            'Route::',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $middleware);
        }
    }

    public function test_http_security_configuration_is_centralized(): void
    {
        $config = file_get_contents(dirname(__DIR__, 2).'/config/http_security.php');
        $middleware = file_get_contents(dirname(__DIR__, 2).'/app/Http/Middleware/ApplyHttpSecurityHeaders.php');

        self::assertIsString($config);
        self::assertIsString($middleware);

        foreach ([
            'Content-Security-Policy',
            'Strict-Transport-Security',
            'X-Frame-Options',
            'X-Content-Type-Options',
            'Referrer-Policy',
            'Permissions-Policy',
        ] as $header) {
            self::assertStringContainsString($header, $middleware);
        }

        foreach ([
            'content_security_policy',
            'strict_transport_security',
            'x_frame_options',
            'x_content_type_options',
            'referrer_policy',
            'permissions_policy',
        ] as $configurationKey) {
            self::assertStringContainsString($configurationKey, $config);
        }
    }
}
