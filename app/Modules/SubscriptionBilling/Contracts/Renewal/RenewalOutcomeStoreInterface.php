<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

interface RenewalOutcomeStoreInterface
{
    public function apply(ApplyRenewalOutcomeCommand $command): RenewalOutcomeResult;
}
