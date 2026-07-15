<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CapabilityKey;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\CapabilityDefinition;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityStatus;

final readonly class CreateCapabilityDefinitionService
{
    public function __construct(private CommercialCatalogueIdentifierGeneratorInterface $identifiers) {}

    public function execute(CreateCapabilityDefinitionCommand $command): CapabilityDefinition
    {
        return new CapabilityDefinition(
            new CapabilityId($this->identifiers->generate()),
            new CapabilityKey($command->capabilityKey),
            $command->name,
            $command->description,
            $command->commercialMeaning,
            CapabilityStatus::Draft,
        );
    }
}
