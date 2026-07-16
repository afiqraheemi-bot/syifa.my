<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueVersionMismatchException;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\Repositories\CapabilityDefinitionRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\CapabilityDefinition;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityId;

/**
 * `occurredAt` is command/audit metadata carried on every privileged mutation.
 * Capability Definition retains it for future durable audit delivery but does
 * not persist it as Domain state — no audit delivery, events, queues, or
 * outbox behavior is implemented here.
 */
final readonly class ActivateCapabilityDefinitionService
{
    public function __construct(private CapabilityDefinitionRepositoryInterface $capabilities) {}

    public function execute(ActivateCapabilityDefinitionCommand $command): CapabilityDefinition
    {
        $capabilityDefinition = $this->requireCapabilityDefinition($command->capabilityId);
        $this->assertExpectedVersion($capabilityDefinition, $command->expectedVersion);

        $updated = $capabilityDefinition->activate();
        $this->capabilities->save($updated);

        return $updated;
    }

    private function requireCapabilityDefinition(string $capabilityId): CapabilityDefinition
    {
        $capabilityDefinition = $this->capabilities->findById(new CapabilityId($capabilityId));

        if ($capabilityDefinition === null) {
            throw new CommercialCatalogueResourceNotFoundException('Capability Definition', $capabilityId);
        }

        return $capabilityDefinition;
    }

    private function assertExpectedVersion(CapabilityDefinition $capabilityDefinition, int $expectedVersion): void
    {
        if ($capabilityDefinition->version() !== $expectedVersion) {
            throw new CommercialCatalogueVersionMismatchException(
                'Capability Definition',
                $capabilityDefinition->id->value,
                $expectedVersion,
                $capabilityDefinition->version(),
            );
        }
    }
}
