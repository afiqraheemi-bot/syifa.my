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
        Config::set('public_website_delivery.asset_origin', 'https://assets.syifa.my');

        $response = $this->get('/api/v1/platform/sessions/current');

        $response->assertHeader(
            'Content-Security-Policy',
            "default-src 'self'; base-uri 'self'; connect-src 'self'; font-src 'self' data:; form-action 'self'; frame-ancestors 'none'; img-src 'self' data: https://assets.syifa.my; object-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; upgrade-insecure-requests",
        );
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $response->assertHeader('Origin-Agent-Cluster', '?1');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader(
            'Permissions-Policy',
            'accelerometer=(), autoplay=(), camera=(), encrypted-media=(), fullscreen=(self), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), midi=(), payment=(), usb=()',
        );
    }

    public function test_development_policy_remains_usable_for_local_browser_tooling(): void
    {
        Config::set('http_security.environment', 'development');
        Config::set('http_security.strict_transport_security.enabled', false);
        Config::set('public_website_delivery.asset_origin', 'http://localhost:8000');

        $response = $this->get('/api/v1/platform/sessions/current');

        $response->assertHeader(
            'Content-Security-Policy',
            "default-src 'self'; base-uri 'self'; connect-src 'self' ws: wss: http://localhost:* http://127.0.0.1:*; font-src 'self' data:; form-action 'self'; frame-ancestors 'none'; img-src 'self' data: http://localhost:8000; object-src 'none'; script-src 'self' 'unsafe-inline' http://localhost:* http://127.0.0.1:*; style-src 'self' 'unsafe-inline'; worker-src 'self' blob:",
        );
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_public_website_assets_can_be_embedded_from_the_configured_asset_origin(): void
    {
        $response = $this->get('/assets/00000000-0000-4000-8000-000000000000');

        $response->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin');
    }

    public function test_template_previews_can_only_be_framed_by_the_same_origin(): void
    {
        Config::set('http_security.environment', 'production');

        $response = $this->get('/templates/preview/syifa-care');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        self::assertStringContainsString(
            "frame-ancestors 'self'",
            (string) $response->headers->get('Content-Security-Policy'),
        );
    }

    public function test_invalid_asset_origin_is_not_added_to_the_content_security_policy(): void
    {
        Config::set('http_security.environment', 'production');
        Config::set('public_website_delivery.asset_origin', 'https://assets.syifa.my; script-src *');

        $response = $this->get('/api/v1/platform/sessions/current');

        $response->assertHeader(
            'Content-Security-Policy',
            "default-src 'self'; base-uri 'self'; connect-src 'self'; font-src 'self' data:; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; object-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; upgrade-insecure-requests",
        );
    }
}
