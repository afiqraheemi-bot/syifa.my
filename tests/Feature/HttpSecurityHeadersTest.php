<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class HttpSecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_applied_with_production_defaults(): void
    {
        Config::set('http_security.environment', 'production');
        Config::set('http_security.strict_transport_security.enabled', true);

        $response = $this->get('/api/v1/platform/sessions/current');

        $response->assertHeader(
            'Content-Security-Policy',
            "default-src 'self'; base-uri 'self'; connect-src 'self'; font-src 'self' data:; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; object-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; upgrade-insecure-requests",
        );
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader(
            'Permissions-Policy',
            'accelerometer=(), autoplay=(), camera=(), encrypted-media=(), fullscreen=(self), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), midi=(), payment=(), usb=()',
        );
    }

    public function test_development_policy_remains_usable_for_local_browser_tooling(): void
    {
        Config::set('http_security.environment', 'development');
        Config::set('http_security.strict_transport_security.enabled', false);

        $response = $this->get('/api/v1/platform/sessions/current');

        $response->assertHeader(
            'Content-Security-Policy',
            "default-src 'self'; base-uri 'self'; connect-src 'self' ws: wss: http://localhost:* http://127.0.0.1:*; font-src 'self' data:; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; object-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; worker-src 'self' blob:",
        );
        $response->assertHeaderMissing('Strict-Transport-Security');
    }
}
