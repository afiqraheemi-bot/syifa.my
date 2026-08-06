<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TenantManagement\Infrastructure\Session;

use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionState;
use App\Modules\TenantManagement\Infrastructure\Authentication\ClinicOwnerAuthenticatable;
use App\Modules\TenantManagement\Infrastructure\Session\LaravelClinicOwnerSessionStore;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;
use Mockery;
use Tests\TestCase;

final class LaravelClinicOwnerSessionStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('session.driver', 'array');
    }

    public function test_establish_rotates_old_identifier_regenerates_csrf_and_stores_only_approved_state(): void
    {
        $session = $this->app->make(Session::class);
        $initialSessionId = $session->getId();

        (new LaravelClinicOwnerSessionStore($session, $this->app->make(AuthFactory::class)))->establish($this->state());

        self::assertNotSame($initialSessionId, $session->getId());
        $stored = $session->get('clinic_owner_authentication');
        self::assertIsArray($stored);
        self::assertSame([
            'tenant_id',
            'authority_id',
            'identity_id',
            'role',
            'authenticated_at',
            'last_activity_at',
            'absolute_expires_at',
        ], array_keys($stored));
    }

    /**
     * Native Guard registration is a pure side effect of establishing an
     * already-verified session — no Eloquent query, no re-verification.
     */
    public function test_establish_logs_in_the_native_guard_and_invalidate_logs_it_out(): void
    {
        $session = $this->app->make(Session::class);
        $store = new LaravelClinicOwnerSessionStore($session, $this->app->make(AuthFactory::class));

        $store->establish($this->state());

        $guard = $this->app->make(AuthFactory::class)->guard('clinic_owner');
        self::assertTrue($guard->check());
        self::assertSame('00000000-0000-4000-8000-000000000002', $guard->id());

        $store->invalidate();
        self::assertFalse($guard->check());
    }

    public function test_invalidate_regenerates_session_and_csrf_state(): void
    {
        $session = $this->app->make(Session::class);
        (new LaravelClinicOwnerSessionStore($session, $this->app->make(AuthFactory::class)))->invalidate();

        self::assertNull($session->get('clinic_owner_authentication'));
    }

    public function test_current_restores_approved_state_from_a_valid_remembered_owner(): void
    {
        $session = $this->app->make(Session::class);
        $user = (new ClinicOwnerAuthenticatable)->forceFill([
            'id' => '00000000-0000-4000-8000-000000000002',
            'tenant_id' => '00000000-0000-4000-8000-000000000001',
            'clinic_owner_identity_id' => '00000000-0000-4000-8000-000000000003',
            'authority_status' => 'active',
            'email_verification_status' => 'verified',
            'email_verified_at' => new DateTimeImmutable,
        ]);
        $guard = Mockery::mock(StatefulGuard::class);
        $guard->shouldReceive('user')->once()->andReturn($user);
        $guard->shouldReceive('viaRemember')->once()->andReturnTrue();
        $auth = Mockery::mock(AuthFactory::class);
        $auth->shouldReceive('guard')->once()->with('clinic_owner')->andReturn($guard);

        $state = (new LaravelClinicOwnerSessionStore($session, $auth, 720))->current();

        self::assertNotNull($state);
        self::assertSame($user->tenant_id, $state->tenantId);
        self::assertSame($user->id, $state->authorityId);
        self::assertSame($user->clinic_owner_identity_id, $state->clinicOwnerIdentityId);
        self::assertIsArray($session->get('clinic_owner_authentication'));
    }

    private function state(): ClinicOwnerSessionState
    {
        return new ClinicOwnerSessionState(
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000003',
            'clinic_owner',
            new DateTimeImmutable('2026-07-13T00:00:00+00:00'),
            new DateTimeImmutable('2026-07-13T00:00:00+00:00'),
            new DateTimeImmutable('2026-07-13T12:00:00+00:00'),
        );
    }
}
