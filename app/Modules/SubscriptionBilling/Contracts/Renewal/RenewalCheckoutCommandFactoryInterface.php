<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

interface RenewalCheckoutCommandFactoryInterface
{
    public function forRenewal(string $renewalId, string $correlationId): ?BeginRenewalCheckoutCommand;
}
