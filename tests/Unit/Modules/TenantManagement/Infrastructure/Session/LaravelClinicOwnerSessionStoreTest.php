<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TenantManagement\Infrastructure\Session;

use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionState;
use App\Modules\TenantManagement\Infrastructure\Session\LaravelClinicOwnerSessionStore;
use DateTimeImmutable;
use Illuminate\Contracts\Session\Session;
use PHPUnit\Framework\TestCase;

final class LaravelClinicOwnerSessionStoreTest extends TestCase
{
    public function test_establish_rotates_old_identifier_regenerates_csrf_and_stores_only_approved_state(): void
    {
        $session = $this->createMock(Session::class);
        $session->expects(self::once())->method('migrate')->with(true);
        $session->expects(self::once())->method('regenerateToken');
        $session->expects(self::once())->method('put')->with(
            'clinic_owner_authentication',
            self::callback(static fn (mixed $state): bool => is_array($state)
                && array_keys($state) === [
                    'tenant_id',
                    'authority_id',
                    'identity_id',
                    'role',
                    'authenticated_at',
                    'last_activity_at',
                    'absolute_expires_at',
                ]),
        );

        (new LaravelClinicOwnerSessionStore($session))->establish($this->state());
    }

    public function test_invalidate_regenerates_session_and_csrf_state(): void
    {
        $session = $this->createMock(Session::class);
        $session->expects(self::once())->method('invalidate');
        $session->expects(self::once())->method('regenerateToken');

        (new LaravelClinicOwnerSessionStore($session))->invalidate();
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
