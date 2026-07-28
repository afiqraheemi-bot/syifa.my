<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

interface RenewalCommercialContextReadInterface
{
    public function currentForRenewal(string $subscriptionId): ?RenewalCommercialContextData;
}
