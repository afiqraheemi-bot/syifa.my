<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Infrastructure\Session;

use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Infrastructure\Session\LaravelPlatformSessionStore;
use DateTimeImmutable;
use Illuminate\Contracts\Session\Session;
use Tests\TestCase;

final class LaravelPlatformSessionStoreTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('session.driver', 'array');
        $this->session = $this->app->make(Session::class);
    }

    public function test_establish_regenerates_the_session_and_persists_only_approved_state(): void
    {
        $store = new LaravelPlatformSessionStore($this->session, 120, 720);
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
        $store = new LaravelPlatformSessionStore($this->session, 120, 720);
        $this->session->put('platform_administration_authentication', ['broken' => true]);

        self::assertNull($store->current(new DateTimeImmutable('2026-07-19T10:00:00Z')));
    }

    public function test_invalidate_clears_the_session_server_side(): void
    {
        $store = new LaravelPlatformSessionStore($this->session, 120, 720);
        $store->establish(
            new PlatformPrincipal('00000000-0000-4000-8000-000000000444', 'website_designer', 'Website Designer'),
            new DateTimeImmutable('2026-07-19T10:00:00Z'),
        );

        $store->invalidate();

        self::assertNull($this->session->get('platform_administration_authentication'));
    }
}
