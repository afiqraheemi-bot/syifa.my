<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class RequestProtectionFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('request-protection-test-cleanup');

        Route::middleware(['web', 'throttle:platform.login'])->post('/request-protection/login', static fn () => response()->json(['ok' => true]));
        Route::middleware(['web', 'throttle:platform.session'])->get('/request-protection/session', static fn () => response()->json(['ok' => true]));
        Route::middleware(['web', 'throttle:platform.admin'])->get('/request-protection/admin', static fn () => response()->json(['ok' => true]));
        Route::middleware(['web', 'throttle:public.default'])->get('/request-protection/public', static fn () => response()->json(['ok' => true]));
    }

    public function test_platform_login_limiter_returns_problem_details_when_exceeded(): void
    {
        config()->set('request_protection.profiles.platform_login.max_attempts', 1);

        $payload = ['email' => 'admin@example.test', 'password' => 'irrelevant'];

        $this->postJson('/request-protection/login', $payload)->assertOk();
        $this->postJson('/request-protection/login', $payload)
            ->assertStatus(429)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'authentication_temporarily_unavailable')
            ->assertJsonStructure(['type', 'title', 'status', 'detail', 'correlation_id']);
    }

    public function test_platform_session_limiter_can_use_the_authenticated_actor_signal(): void
    {
        config()->set('request_protection.profiles.platform_session.max_attempts', 1);

        $this
            ->withSession([
                'platform_administration_authentication' => [
                    'platform_identity_id' => '00000000-0000-4000-8000-000000000601',
                    'role' => 'super_admin',
                    'name' => 'Admin User',
                    'authenticated_at' => '2026-07-20T00:00:00+00:00',
                    'last_activity_at' => '2026-07-20T00:00:00+00:00',
                    'idle_expires_at' => '2026-07-20T02:00:00+00:00',
                    'absolute_expires_at' => '2026-07-20T12:00:00+00:00',
                ],
            ])
            ->getJson('/request-protection/session')
            ->assertOk();

        $this
            ->withSession([
                'platform_administration_authentication' => [
                    'platform_identity_id' => '00000000-0000-4000-8000-000000000601',
                    'role' => 'super_admin',
                    'name' => 'Admin User',
                    'authenticated_at' => '2026-07-20T00:00:00+00:00',
                    'last_activity_at' => '2026-07-20T00:00:00+00:00',
                    'idle_expires_at' => '2026-07-20T02:00:00+00:00',
                    'absolute_expires_at' => '2026-07-20T12:00:00+00:00',
                ],
            ])
            ->getJson('/request-protection/session')
            ->assertStatus(429)
            ->assertJsonPath('type', 'session_temporarily_unavailable');
    }

    public function test_platform_administration_limiter_returns_admin_profile_response(): void
    {
        config()->set('request_protection.profiles.platform_administration.max_attempts', 1);

        $this->getJson('/request-protection/admin')->assertOk();
        $this->getJson('/request-protection/admin')
            ->assertStatus(429)
            ->assertJsonPath('type', 'administration_temporarily_unavailable');
    }

    public function test_public_limiter_is_available_for_future_public_routes(): void
    {
        config()->set('request_protection.profiles.public.max_attempts', 1);

        $this->getJson('/request-protection/public')->assertOk();
        $this->getJson('/request-protection/public')
            ->assertStatus(429)
            ->assertJsonPath('type', 'public_access_temporarily_unavailable');
    }

    public function test_existing_routes_have_the_correct_named_limiter_assignments(): void
    {
        self::assertRouteContainsMiddleware('POST', 'api/v1/platform/sessions', 'throttle:platform.login');
        self::assertRouteContainsMiddleware('GET', 'api/v1/platform/sessions/current', 'throttle:platform.session');
        self::assertRouteContainsMiddleware('DELETE', 'api/v1/platform/sessions/current', 'throttle:platform.session');
        self::assertRouteContainsMiddleware('GET', 'api/v1/platform/commercial-catalogue/plans', 'throttle:platform.admin');
        self::assertRouteContainsMiddleware('POST', 'api/v1/sessions', 'throttle:clinic-owner-session');
    }

    private static function assertRouteContainsMiddleware(string $method, string $uri, string $middleware): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
                self::assertContains($middleware, $route->gatherMiddleware());

                return;
            }
        }

        self::fail(sprintf('Route %s %s was not found.', $method, $uri));
    }
}
