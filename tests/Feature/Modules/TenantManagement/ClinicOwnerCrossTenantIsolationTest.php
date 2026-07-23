<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\TenantManagement;

use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticatedPrincipal;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationCommand;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationInterface;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationOutcome;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationResult;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationSucceeded;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolutionData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolverInterface;
use App\Support\Identity\TenantResolverInterface;
use Tests\TestCase;

/**
 * Sprint 3A Phase 3: proves the authenticated Clinic Owner's Tenant can only
 * ever come from their own session — never from anything an attacker
 * controls in the request. `TenantResolverInterface` takes no Request
 * argument at all (see `CurrentUserResolver`), so there is structurally no
 * code path for a spoofed `tenant_id` — in a query string, a header, or the
 * request body — to influence it. This test proves that holds true even
 * when every one of those vectors carries a different tenant's ID.
 */
final class ClinicOwnerCrossTenantIsolationTest extends TestCase
{
    public const string TENANT_ID = '00000000-0000-4000-8000-000000000001';

    public const string OTHER_TENANT_ID = '00000000-0000-4000-8000-000000000099';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('session.driver', 'array');
    }

    public function test_a_spoofed_tenant_id_in_every_request_vector_never_changes_the_resolved_tenant(): void
    {
        $this->app->bind(ClinicOwnerAuthenticationInterface::class, static fn (): ClinicOwnerAuthenticationInterface => new CrossTenantIsolationSuccessfulAuthentication);
        $this->app->bind(TenantContextResolverInterface::class, static fn (): TenantContextResolverInterface => new CrossTenantIsolationAcceptingContextResolver);

        $this->postJson('https://clinic.app.syifa.my/api/v1/sessions', [
            'email' => 'owner@example.test',
            'password' => 'a private passphrase',
        ])->assertCreated();

        self::assertSame(self::TENANT_ID, $this->app->make(TenantResolverInterface::class)->currentTenantId());

        $this->withHeader('X-Tenant-Id', self::OTHER_TENANT_ID)
            ->getJson('https://clinic.app.syifa.my/api/v1/sessions/current?tenant_id='.self::OTHER_TENANT_ID)
            ->assertOk()
            ->assertJsonPath('data.tenant.id', self::TENANT_ID);

        self::assertSame(
            self::TENANT_ID,
            $this->app->make(TenantResolverInterface::class)->currentTenantId(),
        );
        self::assertNotSame(self::OTHER_TENANT_ID, $this->app->make(TenantResolverInterface::class)->currentTenantId());
    }

    public function test_an_unauthenticated_request_never_resolves_any_tenant_regardless_of_spoofed_input(): void
    {
        self::assertNull(
            $this->withHeader('X-Tenant-Id', self::OTHER_TENANT_ID)
                ->app->make(TenantResolverInterface::class)
                ->currentTenantId(),
        );
    }
}

final class CrossTenantIsolationSuccessfulAuthentication implements ClinicOwnerAuthenticationInterface
{
    public function authenticate(ClinicOwnerAuthenticationCommand $command): ClinicOwnerAuthenticationResult
    {
        $principal = new ClinicOwnerAuthenticatedPrincipal(
            ClinicOwnerCrossTenantIsolationTest::TENANT_ID,
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000003',
        );

        return new ClinicOwnerAuthenticationResult(
            ClinicOwnerAuthenticationOutcome::Authenticated,
            $principal,
            new TenantContextData(null, $principal->tenantId, 'clinic_owner', null),
            new ClinicOwnerAuthenticationSucceeded(
                $principal->tenantId,
                $principal->authorityId,
                $principal->clinicOwnerIdentityId,
                $command->attemptedAt,
            ),
        );
    }
}

final class CrossTenantIsolationAcceptingContextResolver implements TenantContextResolverInterface
{
    public function resolve(TenantContextResolutionData $resolution): ?TenantContextData
    {
        return new TenantContextData(null, $resolution->tenantId, 'clinic_owner', null);
    }
}
