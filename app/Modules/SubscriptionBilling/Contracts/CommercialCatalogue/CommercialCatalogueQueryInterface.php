<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue;

interface CommercialCatalogueQueryInterface
{
    public function findPlan(string $planId): ?PlanData;

    public function findBillingOption(string $billingOptionId): ?BillingOptionData;

    public function findPlanOffering(string $planOfferingId): ?PlanOfferingData;

    public function findCapability(string $capabilityId): ?CapabilityDefinitionData;
}
