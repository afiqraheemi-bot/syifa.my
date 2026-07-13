<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\TenantManagement\TenantIdentity;

use App\Modules\TenantManagement\Application\TenantIdentity\GetClinicOwnerIdentityService;
use App\Modules\TenantManagement\Contracts\TenantIdentity\ClinicOwnerIdentityData;
use App\Modules\TenantManagement\Contracts\TenantIdentity\ClinicOwnerIdentityLookupInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ClinicOwnerIdentityContractsTest extends TestCase
{
    public function test_lookup_contract_requires_both_tenant_and_identity_scope(): void
    {
        $expectedTenantId = '00000000-0000-4000-8000-000000000010';
        $lookup = new class($expectedTenantId) implements ClinicOwnerIdentityLookupInterface
        {
            public function __construct(private readonly string $expectedTenantId) {}

            public function findActiveForTenant(
                string $tenantId,
                string $clinicOwnerIdentityId,
            ): ?ClinicOwnerIdentityData {
                if ($tenantId !== $this->expectedTenantId) {
                    return null;
                }

                return new ClinicOwnerIdentityData(
                    tenantId: $tenantId,
                    clinicOwnerAuthorityId: '00000000-0000-4000-8000-000000000011',
                    clinicOwnerIdentityId: $clinicOwnerIdentityId,
                    email: 'owner@example.test',
                    name: 'Clinic Owner',
                    authorityStatus: 'active',
                    establishedAt: new DateTimeImmutable('2026-07-13T10:00:00+08:00'),
                    revokedAt: null,
                );
            }
        };

        $service = new GetClinicOwnerIdentityService($lookup);
        $identityId = new ClinicOwnerIdentityId('00000000-0000-4000-8000-000000000012');
        $identity = $service->execute(new TenantId($expectedTenantId), $identityId);
        $foreignIdentity = $service->execute(
            new TenantId('00000000-0000-4000-8000-000000000013'),
            $identityId,
        );

        self::assertNotNull($identity);
        self::assertSame($expectedTenantId, $identity->tenantId);
        self::assertSame($identityId->value, $identity->clinicOwnerIdentityId);
        self::assertNull($foreignIdentity);
    }

    public function test_identity_contract_data_is_immutable(): void
    {
        self::assertTrue((new ReflectionClass(ClinicOwnerIdentityData::class))->isReadOnly());
    }
}
