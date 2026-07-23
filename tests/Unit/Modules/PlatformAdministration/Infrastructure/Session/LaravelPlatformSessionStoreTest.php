<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Infrastructure\Session;

use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Infrastructure\Authentication\PlatformIdentityAuthenticatable;
use App\Modules\PlatformAdministration\Infrastructure\Session\LaravelPlatformSessionStore;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Session\Session;
use Tests\TestCase;

final class LaravelPlatformSessionStoreTest extends TestCase
{
    private Session $session;

    private AuthFactory $auth;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('session.driver', 'array');
        $this->session = $this->app->make(Session::class);
        $this->auth = $this->app->make(AuthFactory::class);
    }

    public function test_establish_regenerates_the_session_and_persists_only_approved_state(): void
    {
        $store = new LaravelPlatformSessionStore($this->session, $this->auth, 120, 720);
        $initialSessionId = $this->session->getId();

        $state = $store->establish(
            new PlatformPrincipal('00000000-0000-4000-8000-000000000333', 'super_admin', 'Super Admin'),
            new DateTimeImmutable('2026-07-19T10:00:00Z'),
        );

        self::assertNotSame($initialSessionId, $this->session->getId());
        self::assertSame('00000000-0000-4000-8000-000000000333', $state->principal->platformIdentityId);
        self::assertSame('super_admin', $state->principal->role);
        self::assertSame('Super Admin', $state->principal->name);

        $stored = $this->session->get('platform_administration_authentication');
        self::assertIsArray($stored);
        self::assertArrayNotHasKey('password', $stored);
        self::assertArrayNotHasKey('token', $stored);
    }

    public function test_current_returns_null_and_invalidates_malformed_state(): void
    {
        $store = new LaravelPlatformSessionStore($this->session, $this->auth, 120, 720);
        $this->session->put('platform_administration_authentication', ['broken' => true]);

        self::assertNull($store->current(new DateTimeImmutable('2026-07-19T10:00:00Z')));
    }

    public function test_invalidate_clears_the_session_server_side(): void
    {
        $store = new LaravelPlatformSessionStore($this->session, $this->auth, 120, 720);
        $store->establish(
            new PlatformPrincipal('00000000-0000-4000-8000-000000000444', 'website_designer', 'Website Designer'),
            new DateTimeImmutable('2026-07-19T10:00:00Z'),
        );

        $store->invalidate();

        self::assertNull($this->session->get('platform_administration_authentication'));
    }

    /**
     * Session Expiration (Sprint 3A Phase 1): an idle- or absolute-expired
     * bespoke session must also clear the native Guard — otherwise
     * `auth:platform_identity` middleware would keep recognizing a request
     * the rest of the module already considers logged out.
     */
    public function test_an_idle_expired_session_is_invalidated_and_also_logs_out_the_native_guard(): void
    {
        $store = new LaravelPlatformSessionStore($this->session, $this->auth, 15, 720);
        $store->establish(
            new PlatformPrincipal('00000000-0000-4000-8000-000000000555', 'website_designer', 'Website Designer'),
            new DateTimeImmutable('2026-07-19T10:00:00Z'),
        );

        $guard = $this->auth->guard('platform_identity');
        $guard->setUser((new PlatformIdentityAuthenticatable)->forceFill([
            'platform_identity_id' => '00000000-0000-4000-8000-000000000555',
        ]));
        self::assertTrue($guard->check());

        $expired = $store->current(new DateTimeImmutable('2026-07-19T10:16:00Z'));

        self::assertNull($expired);
        self::assertNull($this->session->get('platform_administration_authentication'));
        self::assertFalse($guard->check());
    }
}
