<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueVersionMismatchException;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanOfferingRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\Money;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\PlanOffering;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\EffectivePeriod;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingId;

final readonly class UpdatePlanOfferingService
{
    public function __construct(private PlanOfferingRepositoryInterface $planOfferings) {}

    public function execute(UpdatePlanOfferingCommand $command): PlanOffering
    {
        $planOffering = $this->requirePlanOffering($command->planOfferingId);
        $this->assertExpectedVersion($planOffering, $command->expectedVersion);

        $updated = new PlanOffering(
            $planOffering->id,
            $planOffering->planId,
            $planOffering->billingOptionId,
            new Money($command->amountMinor, $command->currencyCode),
            new EffectivePeriod($command->effectiveStart, $command->effectiveEnd),
            $planOffering->status,
            $planOffering->configurationVersion,
            $command->capabilityConfigurationReference,
            $command->displayOrder,
            $planOffering->version(),
        );

        $this->planOfferings->save($updated);

        return $updated;
    }

    private function requirePlanOffering(string $planOfferingId): PlanOffering
    {
        $planOffering = $this->planOfferings->findById(new PlanOfferingId($planOfferingId));

        if ($planOffering === null) {
            throw new CommercialCatalogueResourceNotFoundException('Plan Offering', $planOfferingId);
        }

        return $planOffering;
    }

    private function assertExpectedVersion(PlanOffering $planOffering, int $expectedVersion): void
    {
        if ($planOffering->version() !== $expectedVersion) {
            throw new CommercialCatalogueVersionMismatchException(
                'Plan Offering',
                $planOffering->id->value,
                $expectedVersion,
                $planOffering->version(),
            );
        }
    }
}
