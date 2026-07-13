<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TenantManagement\Infrastructure\TenantContext;

use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextAssignmentData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolutionData;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerEmail;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentity;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerName;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantReactivationReadiness;
use App\Modules\TenantManagement\Infrastructure\TenantContext\ClinicOwnerTenantContextResolver;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClinicOwnerTenantContextResolverTest extends TestCase
{
    public function test_valid_clinic_owner_context_contains_only_the_required_runtime_data(): void
    {
        $tenant = $this->activeTenant(1);
        $repository = $this->repositoryWith($tenant);
        $resolution = $this->resolution($tenant);
        $context = (new ClinicOwnerTenantContextResolver($repository))->resolve(
            $resolution,
        );

        self::assertSame($this->uuid(10), $resolution->clinicOwnerAuthorityId);
        self::assertSame($this->uuid(20), $resolution->clinicOwnerIdentityId);
        self::assertNotNull($context);
        self::assertSame($tenant->id->value, $context->tenantId);
        self::assertSame('clinic_owner', $context->role);
        self::assertNull($context->platformIdentityId);
        self::assertNull($context->assignment);
        self::assertSame(
            ['platformIdentityId', 'tenantId', 'role', 'assignment'],
            array_keys(get_object_vars($context)),
        );
    }

    public function test_reactivated_tenant_is_eligible(): void
    {
        $tenant = $this->activeTenant(1);
        $tenant->suspend($this->time('10:02:00'));
        $tenant->reactivate(
            new TenantReactivationReadiness(true, true, true, true, true),
            $this->time('10:03:00'),
        );

        self::assertNotNull(
            (new ClinicOwnerTenantContextResolver($this->repositoryWith($tenant)))
                ->resolve($this->resolution($tenant)),
        );
    }

    public function test_unknown_tenant_fails_closed(): void
    {
        $tenant = $this->activeTenant(1);

        self::assertNull(
            (new ClinicOwnerTenantContextResolver(new RecordingTenantRepository))
                ->resolve($this->resolution($tenant)),
        );
    }

    public function test_ineligible_tenant_lifecycles_fail_closed(): void
    {
        $provisioning = $this->provisioningTenant(1);
        $suspended = $this->activeTenant(2);
        $suspended->suspend($this->time('10:02:00'));
        $offboarding = $this->activeTenant(3);
        $offboarding->beginOffboarding($this->time('10:02:00'));
        $deleted = $this->activeTenant(4);
        $deleted->beginOffboarding($this->time('10:02:00'));
        $deleted->delete($this->time('10:03:00'));
        $anonymized = $this->activeTenant(5);
        $anonymized->beginOffboarding($this->time('10:02:00'));
        $anonymized->anonymize($this->time('10:03:00'));

        foreach ([
            [$provisioning, 10, 20],
            [$suspended, 11, 21],
            [$offboarding, 12, 22],
            [$deleted, 13, 23],
            [$anonymized, 14, 24],
        ] as [$tenant, $authoritySuffix, $identitySuffix]) {
            self::assertNull(
                (new ClinicOwnerTenantContextResolver($this->repositoryWith($tenant)))
                    ->resolve($this->resolution($tenant, $authoritySuffix, $identitySuffix)),
            );
        }
    }

    public function test_revoked_authority_fails_closed(): void
    {
        $tenant = $this->activeTenant(1);
        $tenant->revokeClinicOwnerAuthority($this->authorityId(10), $this->time('10:02:00'));

        self::assertNull(
            (new ClinicOwnerTenantContextResolver($this->repositoryWith($tenant)))
                ->resolve($this->resolution($tenant)),
        );
    }

    public function test_transferred_authority_invalidates_previous_references(): void
    {
        $tenant = $this->activeTenant(1);
        $previousResolution = $this->resolution($tenant);
        $tenant->transferClinicOwnerAuthority(
            $this->authorityId(10),
            $this->authorityId(11),
            $this->identity(21),
            $this->time('10:02:00'),
        );
        $resolver = new ClinicOwnerTenantContextResolver($this->repositoryWith($tenant));

        self::assertNull($resolver->resolve($previousResolution));
        self::assertNotNull($resolver->resolve($this->resolution($tenant, 11, 21)));
    }

    public function test_authority_and_identity_mismatches_fail_closed(): void
    {
        $tenant = $this->activeTenant(1);
        $repository = $this->repositoryWith($tenant);
        $resolver = new ClinicOwnerTenantContextResolver($repository);

        self::assertNull($resolver->resolve($this->resolution($tenant, 99, 20)));
        self::assertNull($resolver->resolve($this->resolution($tenant, 10, 99)));
    }

    public function test_cross_tenant_identifier_substitution_fails_closed(): void
    {
        $firstTenant = $this->activeTenant(1);
        $secondTenant = $this->activeTenant(2);
        $repository = $this->repositoryWith($firstTenant);

        self::assertNull(
            (new ClinicOwnerTenantContextResolver($repository))->resolve(
                $this->resolution($firstTenant, 11, 21),
            ),
        );
        self::assertNotSame(
            $firstTenant->activeClinicOwnerAuthority()?->tenantId->value,
            $secondTenant->activeClinicOwnerAuthority()?->tenantId->value,
        );
    }

    #[DataProvider('unsupportedRoles')]
    public function test_unsupported_roles_fail_closed(string $role): void
    {
        $tenant = $this->activeTenant(1);
        $resolution = $this->resolution($tenant, role: $role);

        self::assertNull(
            (new ClinicOwnerTenantContextResolver($this->repositoryWith($tenant)))
                ->resolve($resolution),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function unsupportedRoles(): iterable
    {
        yield 'website designer' => ['website_designer'];
        yield 'super admin' => ['super_admin'];
        yield 'public visitor' => ['public_visitor'];
        yield 'unknown' => ['unknown_role'];
    }

    public function test_platform_identity_and_assignment_fail_closed(): void
    {
        $tenant = $this->activeTenant(1);
        $resolver = new ClinicOwnerTenantContextResolver($this->repositoryWith($tenant));
        $assignment = new TenantContextAssignmentData(
            $this->uuid(30),
            $this->uuid(31),
            $this->uuid(32),
            $tenant->id->value,
        );

        self::assertNull($resolver->resolve($this->resolution(
            $tenant,
            platformIdentityId: $this->uuid(32),
        )));
        self::assertNull($resolver->resolve($this->resolution($tenant, assignment: $assignment)));
    }

    public function test_each_missing_clinic_owner_reference_fails_closed_without_inference(): void
    {
        $tenant = $this->activeTenant(1);
        $repository = $this->repositoryWith($tenant);
        $resolver = new ClinicOwnerTenantContextResolver($repository);

        self::assertNull($resolver->resolve(new TenantContextResolutionData(
            platformIdentityId: null,
            tenantId: $tenant->id->value,
            role: 'clinic_owner',
            assignment: null,
            clinicOwnerAuthorityId: null,
            clinicOwnerIdentityId: $this->uuid(20),
        )));
        self::assertNull($resolver->resolve(new TenantContextResolutionData(
            platformIdentityId: null,
            tenantId: $tenant->id->value,
            role: 'clinic_owner',
            assignment: null,
            clinicOwnerAuthorityId: $this->uuid(10),
            clinicOwnerIdentityId: null,
        )));
        self::assertNull($resolver->resolve(new TenantContextResolutionData(
            platformIdentityId: null,
            tenantId: $tenant->id->value,
            role: 'clinic_owner',
            assignment: null,
        )));
        self::assertSame(0, $repository->findCount);
    }

    #[DataProvider('unsupportedRoles')]
    public function test_deferred_roles_cannot_exploit_nullable_owner_references(string $role): void
    {
        $tenant = $this->activeTenant(1);
        $repository = $this->repositoryWith($tenant);
        $resolver = new ClinicOwnerTenantContextResolver($repository);

        self::assertNull($resolver->resolve(new TenantContextResolutionData(
            platformIdentityId: null,
            tenantId: $tenant->id->value,
            role: $role,
            assignment: null,
        )));
        self::assertSame(0, $repository->findCount);
    }

    public function test_resolver_reloads_tenant_on_every_call(): void
    {
        $tenant = $this->activeTenant(1);
        $repository = $this->repositoryWith($tenant);
        $resolver = new ClinicOwnerTenantContextResolver($repository);
        $resolution = $this->resolution($tenant);

        self::assertNotNull($resolver->resolve($resolution));
        self::assertNotNull($resolver->resolve($resolution));
        self::assertSame(2, $repository->findCount);
    }

    public function test_revocation_after_success_is_enforced_immediately(): void
    {
        $tenant = $this->activeTenant(1);
        $repository = $this->repositoryWith($tenant);
        $resolver = new ClinicOwnerTenantContextResolver($repository);
        $resolution = $this->resolution($tenant);

        self::assertNotNull($resolver->resolve($resolution));
        $tenant->revokeClinicOwnerAuthority($this->authorityId(10), $this->time('10:02:00'));
        self::assertNull($resolver->resolve($resolution));
        self::assertSame(2, $repository->findCount);
    }

    public function test_suspension_after_success_is_enforced_immediately(): void
    {
        $tenant = $this->activeTenant(1);
        $repository = $this->repositoryWith($tenant);
        $resolver = new ClinicOwnerTenantContextResolver($repository);
        $resolution = $this->resolution($tenant);

        self::assertNotNull($resolver->resolve($resolution));
        $tenant->suspend($this->time('10:02:00'));
        self::assertNull($resolver->resolve($resolution));
        self::assertSame(2, $repository->findCount);
    }

    private function resolution(
        Tenant $tenant,
        int $authoritySuffix = 10,
        int $identitySuffix = 20,
        string $role = 'clinic_owner',
        ?string $platformIdentityId = null,
        ?TenantContextAssignmentData $assignment = null,
    ): TenantContextResolutionData {
        return new TenantContextResolutionData(
            platformIdentityId: $platformIdentityId,
            tenantId: $tenant->id->value,
            role: $role,
            assignment: $assignment,
            clinicOwnerAuthorityId: $this->uuid($authoritySuffix),
            clinicOwnerIdentityId: $this->uuid($identitySuffix),
        );
    }

    private function activeTenant(int $suffix): Tenant
    {
        $tenant = $this->provisioningTenant($suffix);
        $tenant->activate($this->time('10:01:00'));

        return $tenant;
    }

    private function provisioningTenant(int $suffix): Tenant
    {
        $tenant = Tenant::provision(new TenantId($this->uuid($suffix)), $this->time('10:00:00'));
        $tenant->establishClinicOwnerAuthority(
            $this->authorityId(9 + $suffix),
            $this->identity(19 + $suffix),
            $this->time('10:00:30'),
        );

        return $tenant;
    }

    private function identity(int $suffix): ClinicOwnerIdentity
    {
        return new ClinicOwnerIdentity(
            new ClinicOwnerIdentityId($this->uuid($suffix)),
            new ClinicOwnerEmail(sprintf('owner%d@example.test', $suffix)),
            new ClinicOwnerName('Clinic Owner'),
        );
    }

    private function authorityId(int $suffix): ClinicOwnerAuthorityId
    {
        return new ClinicOwnerAuthorityId($this->uuid($suffix));
    }

    private function repositoryWith(Tenant $tenant): RecordingTenantRepository
    {
        $repository = new RecordingTenantRepository;
        $repository->add($tenant);

        return $repository;
    }

    private function time(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-13T'.$time.'+08:00');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
