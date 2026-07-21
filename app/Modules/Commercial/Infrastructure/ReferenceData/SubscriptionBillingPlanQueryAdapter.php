<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Infrastructure\ReferenceData;

use App\Modules\Commercial\Contracts\ReferenceData\PlanQueryInterface;
use App\Modules\Commercial\Contracts\ReferenceData\PlanReferenceData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;

final readonly class SubscriptionBillingPlanQueryAdapter implements PlanQueryInterface
{
    public function __construct(private CommercialCatalogueQueryInterface $catalogue) {}

    public function findActivePlan(string $planId): ?PlanReferenceData
    {
        $plan = $this->catalogue->findPlan($planId);

        if ($plan === null || $plan->status !== 'active') {
            return null;
        }

        return new PlanReferenceData($plan->planId, $plan->code, $plan->name, $plan->status);
    }
}
