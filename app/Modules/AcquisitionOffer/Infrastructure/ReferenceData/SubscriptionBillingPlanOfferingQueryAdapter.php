<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Infrastructure\ReferenceData;

use App\Modules\AcquisitionOffer\Contracts\ReferenceData\PlanOfferingQueryInterface;
use App\Modules\AcquisitionOffer\Contracts\ReferenceData\PlanOfferingReferenceData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanOfferingCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use DateTimeImmutable;

final readonly class SubscriptionBillingPlanOfferingQueryAdapter implements PlanOfferingQueryInterface
{
    public function __construct(
        private PlanOfferingCatalogueQueryInterface $planOfferingCatalogue,
        private CommercialCatalogueQueryInterface $catalogue,
    ) {}

    public function listAvailable(string $effectiveDate): array
    {
        $offerings = [];

        foreach ($this->planOfferingCatalogue->listPlanOfferings(new OffsetPaginationInput(1, 100))->items as $offering) {
            $plan = $this->catalogue->findPlan($offering->planId);
            $billingOption = $this->catalogue->findBillingOption($offering->billingOptionId);

            if (
                $plan === null
                || $billingOption === null
                || $plan->status !== 'active'
                || $billingOption->availability !== 'available'
                || $billingOption->recurrenceClassification !== 'recurring'
                || $offering->status !== 'active'
                || $offering->currencyCode !== 'MYR'
                || ! $this->isEffective($offering->effectiveStart, $offering->effectiveEnd, $effectiveDate)
            ) {
                continue;
            }

            $offerings[] = new PlanOfferingReferenceData(
                $offering->planOfferingId,
                $offering->planId,
                $offering->billingOptionId,
                $plan->name,
                $billingOption->name,
                $offering->amountMinor,
                $offering->currencyCode,
                $offering->effectiveStart,
                $this->billingPeriodEnd($offering->effectiveStart, $billingOption->intervalUnit, $billingOption->intervalCount),
                $offering->configurationVersion,
                $offering->capabilityConfigurationReference,
                $offering->displayOrder,
            );
        }

        return $offerings;
    }

    public function resolveForCheckout(string $planOfferingId, string $effectiveDate): ?PlanOfferingReferenceData
    {
        $offering = $this->catalogue->findPlanOffering($planOfferingId);

        if ($offering === null || $offering->currencyCode !== 'MYR' || $offering->status !== 'active') {
            return null;
        }

        $plan = $this->catalogue->findPlan($offering->planId);
        $billingOption = $this->catalogue->findBillingOption($offering->billingOptionId);

        if (
            $plan === null
            || $billingOption === null
            || $plan->status !== 'active'
            || $billingOption->availability !== 'available'
            || $billingOption->recurrenceClassification !== 'recurring'
            || ! $this->isEffective($offering->effectiveStart, $offering->effectiveEnd, $effectiveDate)
        ) {
            return null;
        }

        return new PlanOfferingReferenceData(
            $offering->planOfferingId,
            $offering->planId,
            $offering->billingOptionId,
            $plan->name,
            $billingOption->name,
            $offering->amountMinor,
            $offering->currencyCode,
            $effectiveDate,
            $this->billingPeriodEnd($effectiveDate, $billingOption->intervalUnit, $billingOption->intervalCount),
            $offering->configurationVersion,
            $offering->capabilityConfigurationReference,
            $offering->displayOrder,
        );
    }

    private function isEffective(string $start, ?string $end, string $date): bool
    {
        return $start <= $date && ($end === null || $end >= $date);
    }

    private function billingPeriodEnd(string $start, ?string $intervalUnit, ?int $intervalCount): string
    {
        if ($intervalUnit === null || $intervalCount === null || $intervalCount < 1) {
            return $start;
        }

        $startDate = new DateTimeImmutable($start);
        $modifier = match ($intervalUnit) {
            'month' => sprintf('+%d months', $intervalCount),
            'quarter' => sprintf('+%d months', $intervalCount * 3),
            'year' => sprintf('+%d years', $intervalCount),
            default => null,
        };

        if ($modifier === null) {
            return $start;
        }

        return $startDate->modify($modifier)->modify('-1 day')->format('Y-m-d');
    }
}
