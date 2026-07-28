<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail;

interface ClinicOwnerSubscriptionDetailReadInterface
{
    public function detailForTenant(string $trustedTenantId): ?ClinicOwnerSubscriptionDetailData;
}
