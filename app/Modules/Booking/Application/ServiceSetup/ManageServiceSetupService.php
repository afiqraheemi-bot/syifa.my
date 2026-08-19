<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\ServiceSetup;

use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Contracts\ServiceSetup\ServiceSetupAuditInterface;
use App\Modules\Booking\Contracts\Transactions\BookingTransactionInterface;
use App\Modules\Booking\Domain\Exceptions\InvalidServiceValueException;
use App\Modules\Booking\Domain\Exceptions\StaleServiceWriteException;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\ServiceDescription;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\ServiceName;
use App\Modules\Booking\Domain\ValueObjects\SortOrder;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;
use DateTimeImmutable;
use Illuminate\Support\Str;

final readonly class ManageServiceSetupService
{
    public function __construct(
        private ServiceRepositoryInterface $services,
        private BookingTransactionInterface $transaction,
        private ServiceSetupAuditInterface $audit,
        private SubscriptionEntitlementLookupInterface $entitlements,
        private string $capabilityKey = 'booking.manage',
    ) {}

    /** @return list<ServiceSetupData> */
    public function list(string $tenantId): array
    {
        $tenant = new TenantId($tenantId);

        return array_map($this->data(...), $this->services->findAll($tenant));
    }

    public function save(SaveServiceCommand $command): ServiceSetupData
    {
        $this->authorizeEntitlement($command->tenantId);
        $tenant = new TenantId($command->tenantId);
        $name = new ServiceName(trim($command->name));
        $description = $this->description($command->description);
        $now = new DateTimeImmutable;

        return $this->transaction->run(function () use ($command, $tenant, $name, $description, $now): ServiceSetupData {
            $service = $command->serviceId === null
                ? Service::register(new ServiceId((string) Str::uuid()), $tenant, $name, $description, new SortOrder($command->sortOrder), $now)
                : $this->existing($tenant, $command);
            $action = $command->serviceId === null ? 'booking.service.create' : 'booking.service.update';
            $previousVersion = $service->version();

            if ($command->serviceId !== null) {
                $this->assertVersion($service, $command->expectedVersion);
                $service->revise($name, $description, new SortOrder($command->sortOrder), $now);
            }
            $this->assertUniqueName($tenant, $service);
            $this->services->save($service);
            $this->audit->record($command->tenantId, $command->actorId, $command->correlationId, $action, $service->id->value, [
                'resource_type' => 'clinic_service',
                'target_label' => sprintf('status=%s;version=%d->%d', $service->status()->value, $previousVersion, $service->version()),
            ]);

            return $this->data($service);
        });
    }

    public function setActive(string $tenantId, string $actorId, string $correlationId, string $serviceId, int $expectedVersion, bool $active): ServiceSetupData
    {
        $this->authorizeEntitlement($tenantId);
        $tenant = new TenantId($tenantId);

        return $this->transaction->run(function () use ($tenantId, $actorId, $correlationId, $tenant, $serviceId, $expectedVersion, $active): ServiceSetupData {
            $service = $this->services->findById($tenant, new ServiceId($serviceId));
            if ($service === null) {
                throw new ServiceSetupNotFoundException('Service was not found.');
            }
            $this->assertVersion($service, $expectedVersion);
            $previousVersion = $service->version();
            $active ? $service->activate(new DateTimeImmutable) : $service->deactivate(new DateTimeImmutable);
            $this->services->save($service);
            $this->audit->record($tenantId, $actorId, $correlationId, $active ? 'booking.service.activate' : 'booking.service.deactivate', $serviceId, [
                'resource_type' => 'clinic_service',
                'target_label' => sprintf('status=%s;version=%d->%d', $service->status()->value, $previousVersion, $service->version()),
            ]);

            return $this->data($service);
        });
    }

    private function existing(TenantId $tenant, SaveServiceCommand $command): Service
    {
        $service = $this->services->findById($tenant, new ServiceId((string) $command->serviceId));
        if ($service === null) {
            throw new ServiceSetupNotFoundException('Service was not found.');
        }

        return $service;
    }

    private function assertVersion(Service $service, ?int $expectedVersion): void
    {
        if ($expectedVersion === null || $service->version() !== $expectedVersion) {
            throw new StaleServiceWriteException('Service changed since it was loaded.');
        }
    }

    private function assertUniqueName(TenantId $tenant, Service $candidate): void
    {
        foreach ($this->services->findAll($tenant) as $service) {
            if ($service->id->value !== $candidate->id->value && mb_strtolower($service->name->value) === mb_strtolower($candidate->name->value)) {
                throw new InvalidServiceValueException('A service with this name already exists.');
            }
        }
    }

    private function description(?string $value): ?ServiceDescription
    {
        $value = $value === null ? null : trim($value);

        return $value === null || $value === '' ? null : new ServiceDescription($value);
    }

    private function authorizeEntitlement(string $tenantId): void
    {
        if (! $this->entitlements->hasCapability($tenantId, $this->capabilityKey, gmdate('Y-m-d\TH:i:s\Z'))) {
            throw new ServiceSetupEntitlementDeniedException('The current subscription does not include Service Setup.');
        }
    }

    private function data(Service $service): ServiceSetupData
    {
        return new ServiceSetupData($service->id->value, $service->name->value, $service->description?->value, $service->sortOrder->value, $service->status()->value, $service->version());
    }
}
