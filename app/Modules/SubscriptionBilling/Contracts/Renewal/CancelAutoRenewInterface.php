<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

interface CancelAutoRenewInterface
{
    public function cancel(AutoRenewCommand $command): AutoRenewOperationResult;
}
