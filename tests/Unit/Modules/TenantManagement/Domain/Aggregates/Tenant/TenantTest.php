<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TenantManagement\Domain\Aggregates\Tenant;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\ClinicOwnerAuthorityEstablished;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\ClinicOwnerAuthorityRevoked;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\ClinicOwnerAuthorityTransferred;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\TenantActivated;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\TenantDeletedOrAnonymized;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\TenantOffboardingStarted;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\TenantProvisioned;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\TenantReactivated;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\TenantSuspended;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidClinicOwnerAuthorityTransitionException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidTenantLifecycleTransitionException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerEmail;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentity;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerName;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantLifecycleStatus;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantReactivationReadiness;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TenantTest extends TestCase
{
    public function test_it_establishes_the_first_clinic_owner_authority(): void
    {
        $tenant = $this->tenant();
        self::assertInstanceOf(TenantProvisioned::class, $tenant->releaseDomainEvents()[0]);

        $authority = $tenant->establishClinicOwnerAuthority(
            $this->authorityId(10),
            $this->identity(20, 'first@example.test'),
            $this->time('10:01:00'),
        );

        self::assertTrue($authority->isActive());
        self::assertTrue($authority->belongsTo($tenant->id));
        self::assertSame($authority, $tenant->activeClinicOwnerAuthority());
        self::assertSame($authority, $tenant->findActiveClinicOwnerAuthorityByIdentity($this->identityId(20)));
        self::assertSame($authority, $tenant->findActiveClinicOwnerAuthorityByEmail(new ClinicOwnerEmail('FIRST@example.test')));
        self::assertInstanceOf(ClinicOwnerAuthorityEstablished::class, $tenant->releaseDomainEvents()[0]);
    }

    public function test_it_rejects_a_second_active_clinic_owner_authority(): void
    {
        $tenant = $this->tenantWithAuthority();

        $this->expectException(InvalidClinicOwnerAuthorityTransitionException::class);
        $tenant->establishClinicOwnerAuthority(
            $this->authorityId(11),
            $this->identity(21, 'second@example.test'),
            $this->time('10:02:00'),
        );
    }

    public function test_it_transfers_clinic_owner_authority_atomically(): void
    {
        $tenant = $this->tenantWithAuthority();
        $tenant->releaseDomainEvents();

        $replacement = $tenant->transferClinicOwnerAuthority(
            $this->authorityId(10),
            $this->authorityId(11),
            $this->identity(21, 'replacement@example.test'),
            $this->time('10:02:00'),
        );

        $previous = $tenant->findClinicOwnerAuthority($this->authorityId(10));
        self::assertNotNull($previous);
        self::assertFalse($previous->isActive());
        self::assertEquals($this->time('10:02:00'), $previous->revokedAt);
        self::assertSame($replacement, $tenant->activeClinicOwnerAuthority());
        self::assertCount(2, $tenant->clinicOwnerAuthorityHistory());
        self::assertInstanceOf(ClinicOwnerAuthorityTransferred::class, $tenant->releaseDomainEvents()[0]);
    }

    public function test_it_rejects_transfer_to_the_same_identity(): void
    {
        $tenant = $this->tenantWithAuthority();

        $this->expectException(InvalidClinicOwnerAuthorityTransitionException::class);
        $tenant->transferClinicOwnerAuthority(
            $this->authorityId(10),
            $this->authorityId(11),
            $this->identity(20, 'renamed@example.test'),
            $this->time('10:02:00'),
        );
    }

    public function test_it_rejects_transfer_from_an_inactive_authority(): void
    {
        $tenant = $this->tenantWithAuthority();
        $tenant->revokeClinicOwnerAuthority($this->authorityId(10), $this->time('10:02:00'));

        $this->expectException(InvalidClinicOwnerAuthorityTransitionException::class);
        $tenant->transferClinicOwnerAuthority(
            $this->authorityId(10),
            $this->authorityId(11),
            $this->identity(21, 'replacement@example.test'),
            $this->time('10:03:00'),
        );
    }

    public function test_it_revokes_authority_and_excludes_it_from_active_lookup(): void
    {
        $tenant = $this->tenantWithAuthority();
        $tenant->releaseDomainEvents();

        $tenant->revokeClinicOwnerAuthority($this->authorityId(10), $this->time('10:02:00'));

        self::assertNull($tenant->activeClinicOwnerAuthority());
        self::assertNull($tenant->findActiveClinicOwnerAuthorityByIdentity($this->identityId(20)));
        self::assertNull($tenant->findActiveClinicOwnerAuthorityByEmail(new ClinicOwnerEmail('first@example.test')));
        self::assertInstanceOf(ClinicOwnerAuthorityRevoked::class, $tenant->releaseDomainEvents()[0]);
    }

    public function test_it_rejects_repeated_revocation(): void
    {
        $tenant = $this->tenantWithAuthority();
        $tenant->revokeClinicOwnerAuthority($this->authorityId(10), $this->time('10:02:00'));

        $this->expectException(InvalidClinicOwnerAuthorityTransitionException::class);
        $tenant->revokeClinicOwnerAuthority($this->authorityId(10), $this->time('10:03:00'));
    }

    public function test_it_rejects_cross_tenant_authority_substitution(): void
    {
        $tenant = $this->tenant(1);
        $otherTenant = $this->tenant(2);
        $otherTenant->establishClinicOwnerAuthority(
            $this->authorityId(12),
            $this->identity(22, 'other@example.test'),
            $this->time('10:01:00'),
        );

        $this->expectException(InvalidClinicOwnerAuthorityTransitionException::class);
        $tenant->revokeClinicOwnerAuthority($this->authorityId(12), $this->time('10:02:00'));
    }

    public function test_it_enforces_the_tenant_lifecycle_and_emits_transition_events(): void
    {
        $tenant = $this->tenantWithAuthority();
        $tenant->releaseDomainEvents();

        $tenant->activate($this->time('10:02:00'));
        self::assertSame(TenantLifecycleStatus::Active, $tenant->status());
        self::assertInstanceOf(TenantActivated::class, $tenant->releaseDomainEvents()[0]);

        $tenant->suspend($this->time('10:03:00'));
        self::assertSame(TenantLifecycleStatus::Suspended, $tenant->status());
        self::assertInstanceOf(TenantSuspended::class, $tenant->releaseDomainEvents()[0]);

        $tenant->reactivate($this->reactivationReadiness(), $this->time('10:04:00'));
        self::assertSame(TenantLifecycleStatus::Reactivated, $tenant->status());
        self::assertInstanceOf(TenantReactivated::class, $tenant->releaseDomainEvents()[0]);

        $tenant->beginOffboarding($this->time('10:05:00'));
        self::assertSame(TenantLifecycleStatus::Offboarding, $tenant->status());
        self::assertInstanceOf(TenantOffboardingStarted::class, $tenant->releaseDomainEvents()[0]);

        $tenant->anonymize($this->time('10:06:00'));
        self::assertSame(TenantLifecycleStatus::Anonymized, $tenant->status());
        $event = $tenant->releaseDomainEvents()[0];
        self::assertInstanceOf(TenantDeletedOrAnonymized::class, $event);
        self::assertSame('anonymized', $event->outcome);
    }

    public function test_it_rejects_invalid_tenant_lifecycle_transitions(): void
    {
        $tenant = $this->tenantWithAuthority();

        $this->expectException(InvalidTenantLifecycleTransitionException::class);
        $tenant->reactivate($this->reactivationReadiness(), $this->time('10:02:00'));
    }

    public function test_it_requires_active_authority_for_activation_and_reactivation(): void
    {
        $tenant = $this->tenant();

        try {
            $tenant->activate($this->time('10:01:00'));
            self::fail('Activation without active authority should fail.');
        } catch (InvalidTenantLifecycleTransitionException) {
            self::assertSame(TenantLifecycleStatus::Provisioning, $tenant->status());
        }

        $tenant->establishClinicOwnerAuthority(
            $this->authorityId(10),
            $this->identity(20, 'first@example.test'),
            $this->time('10:02:00'),
        );
        $tenant->activate($this->time('10:03:00'));
        $tenant->suspend($this->time('10:04:00'));
        $tenant->revokeClinicOwnerAuthority($this->authorityId(10), $this->time('10:05:00'));

        $this->expectException(InvalidTenantLifecycleTransitionException::class);
        $tenant->reactivate($this->reactivationReadiness(), $this->time('10:06:00'));
    }

    public function test_terminal_tenant_rejects_authority_changes_and_lifecycle_restoration(): void
    {
        $tenant = $this->tenantWithAuthority();
        $tenant->activate($this->time('10:02:00'));
        $tenant->beginOffboarding($this->time('10:03:00'));
        $tenant->delete($this->time('10:04:00'));

        try {
            $tenant->establishClinicOwnerAuthority(
                $this->authorityId(11),
                $this->identity(21, 'replacement@example.test'),
                $this->time('10:05:00'),
            );
            self::fail('A closed Tenant should reject authority changes.');
        } catch (InvalidClinicOwnerAuthorityTransitionException) {
            self::assertSame(TenantLifecycleStatus::Deleted, $tenant->status());
        }

        $this->expectException(InvalidTenantLifecycleTransitionException::class);
        $tenant->reactivate($this->reactivationReadiness(), $this->time('10:06:00'));
    }

    public function test_it_rejects_reactivation_without_complete_revalidation(): void
    {
        $tenant = $this->tenantWithAuthority();
        $tenant->activate($this->time('10:02:00'));
        $tenant->suspend($this->time('10:03:00'));

        $this->expectException(InvalidTenantLifecycleTransitionException::class);
        $tenant->reactivate(
            new TenantReactivationReadiness(true, true, true, false, true),
            $this->time('10:04:00'),
        );
    }

    private function tenantWithAuthority(): Tenant
    {
        $tenant = $this->tenant();
        $tenant->establishClinicOwnerAuthority(
            $this->authorityId(10),
            $this->identity(20, 'first@example.test'),
            $this->time('10:01:00'),
        );

        return $tenant;
    }

    private function tenant(int $suffix = 1): Tenant
    {
        return Tenant::provision(
            new TenantId($this->uuid($suffix)),
            $this->time('10:00:00'),
        );
    }

    private function authorityId(int $suffix): ClinicOwnerAuthorityId
    {
        return new ClinicOwnerAuthorityId($this->uuid($suffix));
    }

    private function identityId(int $suffix): ClinicOwnerIdentityId
    {
        return new ClinicOwnerIdentityId($this->uuid($suffix));
    }

    private function identity(int $suffix, string $email): ClinicOwnerIdentity
    {
        return new ClinicOwnerIdentity(
            $this->identityId($suffix),
            new ClinicOwnerEmail($email),
            new ClinicOwnerName('Clinic Owner'),
        );
    }

    private function time(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-13T'.$time.'+08:00');
    }

    private function reactivationReadiness(): TenantReactivationReadiness
    {
        return new TenantReactivationReadiness(true, true, true, true, true);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
