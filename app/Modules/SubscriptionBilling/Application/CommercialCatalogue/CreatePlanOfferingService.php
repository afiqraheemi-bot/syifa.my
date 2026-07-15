<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanOfferingCommand;
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

    public function __construct(private CommercialCatalogueIdentifierGeneratorInterface $identifiers) {}

    public function execute(CreatePlanOfferingCommand $command): PlanOffering
    {
        return new PlanOffering(
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
    }
}
