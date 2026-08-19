<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Application;

use App\Modules\Booking\Application\ServiceSetup\ManageServiceSetupService;
use App\Modules\Booking\Application\ServiceSetup\SaveServiceCommand;
use App\Modules\Booking\Application\ServiceSetup\ServiceSetupEntitlementDeniedException;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Contracts\ServiceSetup\ServiceSetupAuditInterface;
use App\Modules\Booking\Contracts\Transactions\BookingTransactionInterface;
use App\Modules\Booking\Domain\Exceptions\StaleServiceWriteException;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;
use PHPUnit\Framework\TestCase;

final class ManageServiceSetupServiceTest extends TestCase
{
    public function test_create_update_and_status_changes_use_authoritative_tenant_and_write_audit(): void
    {
        $fixture = new ServiceSetupFixture;
        $application = $fixture->application();

        $created = $application->save($this->command());
        $updated = $application->save($this->command($created->id, $created->version, 'Dental Care'));
        $inactive = $application->setActive($this->uuid(1), $this->uuid(2), $this->uuid(4), $created->id, $updated->version, false);

        self::assertSame('Dental Care', $updated->name);
        self::assertSame('inactive', $inactive->status);
        self::assertSame(3, $inactive->version);
        self::assertSame([
            'booking.service.create',
            'booking.service.update',
            'booking.service.deactivate',
        ], $fixture->auditActions);
    }

    public function test_stale_update_is_rejected_before_persistence_or_audit(): void
    {
        $fixture = new ServiceSetupFixture;
        $application = $fixture->application();
        $created = $application->save($this->command());

        $this->expectException(StaleServiceWriteException::class);
        try {
            $application->save($this->command($created->id, 999, 'Stale'));
        } finally {
            self::assertCount(1, $fixture->auditActions);
            self::assertSame('Consultation', $application->list($this->uuid(1))[0]->name);
        }
    }

    public function test_missing_entitlement_fails_closed_without_writing(): void
    {
        $fixture = new ServiceSetupFixture(entitled: false);

        $this->expectException(ServiceSetupEntitlementDeniedException::class);
        try {
            $fixture->application()->save($this->command());
        } finally {
            self::assertSame([], $fixture->services);
            self::assertSame([], $fixture->auditActions);
        }
    }

    private function command(?string $serviceId = null, ?int $version = null, string $name = 'Consultation'): SaveServiceCommand
    {
        return new SaveServiceCommand(
            $this->uuid(1),
            $this->uuid(2),
            $this->uuid(3),
            $serviceId,
            $name,
            'General consultation',
            1,
            $version,
        );
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final class ServiceSetupFixture implements BookingTransactionInterface, ServiceRepositoryInterface, ServiceSetupAuditInterface, SubscriptionEntitlementLookupInterface
{
    /** @var array<string, Service> */
    public array $services = [];

    /** @var list<string> */
    public array $auditActions = [];

    public function __construct(private readonly bool $entitled = true) {}

    public function application(): ManageServiceSetupService
    {
        return new ManageServiceSetupService($this, $this, $this, $this);
    }

    public function findById(TenantId $tenantId, ServiceId $serviceId): ?Service
    {
        $service = $this->services[$serviceId->value] ?? null;

        return $service?->tenantId->value === $tenantId->value ? $service : null;
    }

    public function findAll(TenantId $tenantId): array
    {
        return array_values(array_filter($this->services, static fn (Service $service): bool => $service->tenantId->value === $tenantId->value));
    }

    public function findActive(TenantId $tenantId): array
    {
        return array_values(array_filter($this->findAll($tenantId), static fn (Service $service): bool => $service->status()->value === 'active'));
    }

    public function existsByName(TenantId $tenantId, string $name): bool
    {
        return array_any($this->findAll($tenantId), static fn (Service $service): bool => $service->name->value === $name);
    }

    public function save(Service $service): void
    {
        $service->synchronizeVersion($service->version() + 1);
        $this->services[$service->id->value] = $service;
    }

    public function run(callable $operation): mixed
    {
        return $operation();
    }

    public function record(string $tenantId, string $actorId, string $correlationId, string $action, string $serviceId, array $metadata): void
    {
        $this->auditActions[] = $action;
    }

    public function hasCapability(string $tenantId, string $capabilityKey, string $effectiveDateTime): bool
    {
        return $this->entitled && $capabilityKey === 'booking.manage';
    }

    public function getActiveCapabilityKeys(string $tenantId, string $effectiveDateTime): array
    {
        return $this->entitled ? ['booking.manage'] : [];
    }
}
