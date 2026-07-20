<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

final class PlatformEdgeSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SymfonyRequest::setTrustedHosts([]);
        SymfonyRequest::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        URL::forceScheme(null);

        Route::middleware('web')->get('/edge-security-probe', static function (Request $request) {
            $request->session()->put('edge_security_probe', 'seen');

            return response()->json([
                'client_ip' => $request->ip(),
                'host' => $request->getHost(),
                'secure' => $request->isSecure(),
                'scheme' => $request->getScheme(),
                'generated_url' => URL::to('/edge-generated'),
            ]);
        });
    }

    protected function tearDown(): void
    {
        SymfonyRequest::setTrustedHosts([]);
        SymfonyRequest::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        URL::forceScheme(null);

        parent::tearDown();
    }

    public function test_trusted_proxy_forwarded_headers_drive_https_host_and_client_ip(): void
    {
        Config::set('app.url', 'https://app.syifa.test');
        Config::set('edge_security.trusted_proxies.proxies', 'REMOTE_ADDR');
        Config::set('edge_security.trusted_hosts.enabled', true);
        Config::set('edge_security.trusted_hosts.hosts', 'app.syifa.test');
        Config::set('edge_security.trusted_hosts.include_app_url_host', false);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.10'])
            ->withHeaders([
                'X-Forwarded-For' => '203.0.113.42, 10.0.0.10',
                'X-Forwarded-Host' => 'app.syifa.test',
                'X-Forwarded-Port' => '443',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('http://origin.internal/edge-security-probe');

        $response
            ->assertOk()
            ->assertJsonPath('client_ip', '203.0.113.42')
            ->assertJsonPath('host', 'app.syifa.test')
            ->assertJsonPath('secure', true)
            ->assertJsonPath('scheme', 'https')
            ->assertJsonPath('generated_url', 'https://app.syifa.test/edge-generated');
    }

    public function test_forwarded_headers_are_ignored_when_proxy_is_not_trusted(): void
    {
        Config::set('app.url', 'https://app.syifa.test');
        Config::set('edge_security.trusted_proxies.proxies', '');
        Config::set('edge_security.trusted_hosts.enabled', true);
        Config::set('edge_security.trusted_hosts.hosts', 'origin.internal');
        Config::set('edge_security.trusted_hosts.include_app_url_host', false);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.10'])
            ->withHeaders([
                'X-Forwarded-For' => '203.0.113.42, 10.0.0.10',
                'X-Forwarded-Host' => 'app.syifa.test',
                'X-Forwarded-Port' => '443',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('http://origin.internal/edge-security-probe');

        $response
            ->assertOk()
            ->assertJsonPath('client_ip', '10.0.0.10')
            ->assertJsonPath('host', 'origin.internal')
            ->assertJsonPath('secure', false)
            ->assertJsonPath('scheme', 'http')
            ->assertJsonPath('generated_url', 'http://origin.internal/edge-generated');
    }

    public function test_trusted_host_enforcement_rejects_unconfigured_hosts(): void
    {
        Config::set('edge_security.trusted_proxies.proxies', '');
        Config::set('edge_security.trusted_hosts.enabled', true);
        Config::set('edge_security.trusted_hosts.hosts', 'app.syifa.test');
        Config::set('edge_security.trusted_hosts.include_app_url_host', false);

        $response = $this
            ->get('http://evil.example/edge-security-probe');

        $response->assertBadRequest();
    }

    public function test_production_session_cookie_configuration_remains_secure(): void
    {
        $sessionConfig = file_get_contents(base_path('config/session.php'));

        self::assertIsString($sessionConfig);
        self::assertStringContainsString("'secure' => env('APP_ENV', 'production') === 'production'", $sessionConfig);
        self::assertStringContainsString('? true', $sessionConfig);
        self::assertTrue((bool) config('session.encrypt'));
        self::assertTrue((bool) config('session.http_only'));
        self::assertSame('lax', config('session.same_site'));
    }
}
