<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\Repositories\CapabilityDefinitionRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CapabilityKey;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\CapabilityDefinition;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityStatus;

final readonly class CreateCapabilityDefinitionService
{
    private const string AUDIT_ACTION = 'commercial_catalogue.capability_definition.create';

    public function __construct(
        private CommercialCatalogueIdentifierGeneratorInterface $identifiers,
        private CapabilityDefinitionRepositoryInterface $capabilities,
        private CapabilityDefinitionAuditTrail $audit,
    ) {}

    public function execute(CreateCapabilityDefinitionCommand $command): CapabilityDefinition
    {
        $capabilityDefinition = new CapabilityDefinition(
            new CapabilityId($this->identifiers->generate()),
            new CapabilityKey($command->capabilityKey),
            $command->name,
            $command->description,
            $command->commercialMeaning,
            CapabilityStatus::Draft,
        );

        $this->capabilities->save($capabilityDefinition);
        $this->audit->record(
            self::AUDIT_ACTION,
            $capabilityDefinition,
            0,
            'none',
            $command->occurredAt,
            $command->actorPlatformIdentityId,
            $command->correlationId,
        );

        return $capabilityDefinition;
    }
}
