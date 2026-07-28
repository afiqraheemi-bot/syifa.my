<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

interface RenewalOutboxInterface
{
    public function add(RenewalIntegrationEvent $event): void;
}
