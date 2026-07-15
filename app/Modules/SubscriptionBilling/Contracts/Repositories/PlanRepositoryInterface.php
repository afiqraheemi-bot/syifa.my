<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Repositories;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Plan;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanCode;

interface PlanRepositoryInterface
{
    public function findById(PlanId $planId): ?Plan;

    public function findByCode(PlanCode $planCode): ?Plan;

    public function save(Plan $plan): void;

    public function existsByCode(PlanCode $planCode): bool;
}
