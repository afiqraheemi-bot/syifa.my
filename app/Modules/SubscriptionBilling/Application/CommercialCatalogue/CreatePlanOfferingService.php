<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanOfferingRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\Money;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\PlanOffering;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\EffectivePeriod;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingStatus;

final readonly class CreatePlanOfferingService
{
    private const string CONFIGURATION_VERSION = '1';

    private const string AUDIT_ACTION = 'commercial_catalogue.plan_offering.create';

    public function __construct(
        private CommercialCatalogueIdentifierGeneratorInterface $identifiers,
        private PlanOfferingRepositoryInterface $planOfferings,
        private PlanOfferingAuditTrail $audit,
    ) {}

    public function execute(CreatePlanOfferingCommand $command): PlanOffering
    {
        $planOffering = new PlanOffering(
            new PlanOfferingId($this->identifiers->generate()),
            new PlanId($command->planId),
            new BillingOptionId($command->billingOptionId),
            new Money($command->amountMinor, $command->currencyCode),
            new EffectivePeriod($command->effectiveStart, $command->effectiveEnd),
            PlanOfferingStatus::Draft,
            self::CONFIGURATION_VERSION,
            $command->capabilityConfigurationReference,
            $command->displayOrder,
        );

        $this->planOfferings->save($planOffering);
        $this->audit->record(
            self::AUDIT_ACTION,
            $planOffering,
            0,
            'none',
            $command->occurredAt,
            $command->actorPlatformIdentityId,
            $command->correlationId,
        );

        return $planOffering;
    }
}
