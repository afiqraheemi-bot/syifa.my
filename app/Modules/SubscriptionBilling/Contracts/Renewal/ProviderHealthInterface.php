<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

interface ProviderHealthInterface
{
    /** @return list<ProviderHealth> */
    public function all(): array;
}
