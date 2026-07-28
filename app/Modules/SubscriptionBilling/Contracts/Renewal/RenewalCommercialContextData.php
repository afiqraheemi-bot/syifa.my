<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

final readonly class RenewalCommercialContextData
{
    public function __construct(
        public string $subscriptionId,
        public string $tenantId,
        public string $clinicRegistrationId,
        public string $planId,
        public string $billingCycleId,
        public string $endsOn,
        public string $status,
        public int $version,
    ) {}
}
