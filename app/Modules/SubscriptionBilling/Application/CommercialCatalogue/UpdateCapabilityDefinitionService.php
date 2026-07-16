<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueVersionMismatchException;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\Repositories\CapabilityDefinitionRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CapabilityKey;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\CapabilityDefinition;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityId;

final readonly class UpdateCapabilityDefinitionService
{
    public function __construct(private CapabilityDefinitionRepositoryInterface $capabilities) {}

    public function execute(UpdateCapabilityDefinitionCommand $command): CapabilityDefinition
    {
        $capabilityDefinition = $this->requireCapabilityDefinition($command->capabilityId);
        $this->assertExpectedVersion($capabilityDefinition, $command->expectedVersion);

        $updated = new CapabilityDefinition(
            $capabilityDefinition->id,
            new CapabilityKey($capabilityDefinition->key->value),
            $command->name,
            $command->description,
            $command->commercialMeaning,
            $capabilityDefinition->status,
            $capabilityDefinition->version(),
        );

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
