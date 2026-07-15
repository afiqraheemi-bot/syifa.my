<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Repositories;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\PlanOffering;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingId;
use Traversable;

interface PlanOfferingRepositoryInterface
{
    public function findById(PlanOfferingId $planOfferingId): ?PlanOffering;

    public function save(PlanOffering $planOffering): void;

    /**
     * @return Traversable<PlanOffering>
     */
    public function findAvailableForDate(string $effectiveDate): Traversable;

    /**
     * @return Traversable<PlanOffering>
     */
    public function findByPlan(PlanId $planId): Traversable;
}
