<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

interface EnableAutoRenewInterface
{
    public function enable(AutoRenewCommand $command): AutoRenewOperationResult;
}
