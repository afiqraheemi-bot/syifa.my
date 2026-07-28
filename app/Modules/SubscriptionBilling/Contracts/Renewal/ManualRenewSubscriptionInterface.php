<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

interface ManualRenewSubscriptionInterface
{
    public function renew(ManualRenewSubscriptionCommand $command): RenewalOperationResult;
}
