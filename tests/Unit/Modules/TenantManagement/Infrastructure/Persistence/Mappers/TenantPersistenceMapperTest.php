<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TenantManagement\Infrastructure\Persistence\Mappers;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidClinicOwnerAuthorityTransitionException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantLifecycleStatus;
use App\Modules\TenantManagement\Infrastructure\Persistence\Mappers\TenantPersistenceMapper;
use App\Modules\TenantManagement\Infrastructure\Persistence\Records\ClinicOwnerAuthorityStorageRecord;
use App\Modules\TenantManagement\Infrastructure\Persistence\Records\TenantStorageRecord;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TenantPersistenceMapperTest extends TestCase
{
    public function test_it_reconstitutes_a_provisioned_tenant_without_domain_events(): void
    {
        $tenant = (new TenantPersistenceMapper)->toDomain(
            new TenantStorageRecord(
                $this->uuid(1),
                'provisioning',
                4,
                new DateTimeImmutable('2026-07-13T10:00:00+08:00'),
            ),
            [],
        );

        self::assertSame($this->uuid(1), $tenant->id->value);
        self::assertSame(TenantLifecycleStatus::Provisioning, $tenant->status());
        self::assertSame(4, $tenant->version());
        self::assertSame([], $tenant->releaseDomainEvents());
    }

    public function test_it_maps_the_tenant_owned_admin_routing_label_both_ways(): void
    {
        $mapper = new TenantPersistenceMapper;
        $tenant = $mapper->toDomain(
            new TenantStorageRecord(
                $this->uuid(1),
                'provisioning',
                4,
                new DateTimeImmutable('2026-07-13T10:00:00+08:00'),
                adminRoutingLabel: 'clinic-one',
            ),
            [],
        );

        self::assertSame('clinic-one', $tenant->adminRoutingLabel()?->value);
        self::assertSame('clinic-one', $mapper->tenantRecord($tenant)->adminRoutingLabel);
    }

    public function test_it_reconstitutes_an_activated_tenant_and_complete_authority_history(): void
    {
        $tenant = (new TenantPersistenceMapper)->toDomain(
            new TenantStorageRecord(
                $this->uuid(1),
                'active',
                8,
                new DateTimeImmutable('2026-07-13T09:00:00+08:00'),
                new DateTimeImmutable('2026-07-13T09:30:00+08:00'),
            ),
            [
                $this->authorityRecord(10, 20, 'revoked', '2026-07-13T11:00:00+08:00'),
                $this->authorityRecord(11, 21, 'active'),
            ],
        );

        self::assertSame(TenantLifecycleStatus::Active, $tenant->status());
        self::assertEquals(
            new DateTimeImmutable('2026-07-13T09:30:00+08:00'),
            $tenant->lifecycleTimestamps()->activatedAt,
        );
        self::assertCount(2, $tenant->clinicOwnerAuthorityHistory());
        self::assertNotNull($tenant->activeClinicOwnerAuthority());
        self::assertSame($this->uuid(11), $tenant->activeClinicOwnerAuthority()?->id->value);
        self::assertFalse($tenant->clinicOwnerAuthorityHistory()[0]->isActive());
    }

    public function test_it_rejects_cross_tenant_authority_storage(): void
    {
        $foreignAuthority = new ClinicOwnerAuthorityStorageRecord(
            $this->uuid(10),
            $this->uuid(2),
            $this->uuid(20),
            'owner@example.test',
            'Clinic Owner',
            'active',
            new DateTimeImmutable('2026-07-13T10:00:00+08:00'),
            null,
        );

        $this->expectException(InvalidClinicOwnerAuthorityTransitionException::class);
        (new TenantPersistenceMapper)->toDomain(
            new TenantStorageRecord(
                $this->uuid(1),
                'active',
                1,
                new DateTimeImmutable('2026-07-13T09:00:00+08:00'),
                new DateTimeImmutable('2026-07-13T09:30:00+08:00'),
            ),
            [$foreignAuthority],
        );
    }

    public function test_it_rejects_multiple_active_authorities_during_reconstitution(): void
    {
        $this->expectException(InvalidClinicOwnerAuthorityTransitionException::class);
        (new TenantPersistenceMapper)->toDomain(
            new TenantStorageRecord(
                $this->uuid(1),
                'active',
                1,
                new DateTimeImmutable('2026-07-13T09:00:00+08:00'),
                new DateTimeImmutable('2026-07-13T09:30:00+08:00'),
            ),
            [
                $this->authorityRecord(10, 20, 'active'),
                $this->authorityRecord(11, 21, 'active'),
            ],
        );
    }

    public function test_it_reconstitutes_clinic_owner_credential_state(): void
    {
        $record = new ClinicOwnerAuthorityStorageRecord(
            $this->uuid(10),
            $this->uuid(1),
            $this->uuid(20),
            'owner@example.test',
            'Clinic Owner',
            'active',
            new DateTimeImmutable('2026-07-13T10:00:00+08:00'),
            null,
            'synthetic-password-hash',
            'verified',
            new DateTimeImmutable('2026-07-13T10:01:00+08:00'),
            5,
            new DateTimeImmutable('2026-07-13T10:16:00+08:00'),
            3,
        );
        $tenant = (new TenantPersistenceMapper)->toDomain(
            new TenantStorageRecord(
                $this->uuid(1),
                'active',
                2,
                new DateTimeImmutable('2026-07-13T09:00:00+08:00'),
                new DateTimeImmutable('2026-07-13T09:30:00+08:00'),
            ),
            [$record],
        );
        $state = $tenant->activeClinicOwnerAuthority()?->credentialState();

        self::assertNotNull($state);
        self::assertTrue($state->isEmailVerified());
        self::assertSame(5, $state->failedAttemptCount);
        self::assertSame(3, $state->credentialVersion);
        self::assertSame('synthetic-password-hash', $state->passwordHashForPersistence());
    }

    private function authorityRecord(
        int $authoritySuffix,
        int $identitySuffix,
        string $status,
        ?string $revokedAt = null,
    ): ClinicOwnerAuthorityStorageRecord {
        return new ClinicOwnerAuthorityStorageRecord(
            $this->uuid($authoritySuffix),
            $this->uuid(1),
            $this->uuid($identitySuffix),
            sprintf('owner%d@example.test', $identitySuffix),
            'Clinic Owner',
            $status,
            new DateTimeImmutable('2026-07-13T10:00:00+08:00'),
            $revokedAt === null ? null : new DateTimeImmutable($revokedAt),
        );
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
