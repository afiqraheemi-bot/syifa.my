<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TenantManagement\Application\Provisioning;

use App\Modules\TenantManagement\Application\Provisioning\ProvisionTenantService;
use App\Modules\TenantManagement\Contracts\Provisioning\ProvisionTenantCommand;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProvisionTenantServiceTest extends TestCase
{
    public function test_it_provisions_the_reserved_tenant_once_and_reuses_the_existing_result(): void
    {
        $repository = new InMemoryProvisioningTenantRepository;
        $service = new ProvisionTenantService($repository);
        $command = new ProvisionTenantCommand(
            '10000000-0000-4000-8000-000000000001',
            '20000000-0000-4000-8000-000000000001',
            new DateTimeImmutable('2026-09-01T00:00:00Z'),
        );

        $created = $service->execute($command);
        $replayed = $service->execute($command);

        self::assertSame('10000000-0000-4000-8000-000000000001', $created->tenantId);
        self::assertSame('provisioning', $created->status);
        self::assertSame(1, $created->version);
        self::assertFalse($created->alreadyExisted);
        self::assertTrue($replayed->alreadyExisted);
        self::assertSame(1, $repository->saveCalls);
    }
}

final class InMemoryProvisioningTenantRepository implements TenantRepositoryInterface
{
    public ?Tenant $tenant = null;

    public int $saveCalls = 0;

    public function find(TenantId $tenantId): ?Tenant
    {
        return $this->tenant?->id->value === $tenantId->value ? $this->tenant : null;
    }

    public function save(Tenant $tenant): void
    {
        $tenant->synchronizePersistenceVersion($tenant->version() + 1);
        $this->tenant = $tenant;
        $this->saveCalls++;
    }
}
