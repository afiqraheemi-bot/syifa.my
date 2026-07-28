<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

interface ClinicOwnerRenewalCheckoutCommandFactoryInterface
{
    public function forTenant(string $trustedTenantId, string $correlationId): ?BeginRenewalCheckoutCommand;
}
