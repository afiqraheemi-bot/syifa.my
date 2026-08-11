<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Subscription;

interface ChangeSubscriptionPlanInterface
{
    public function change(ChangeSubscriptionPlanCommand $command): string;
}
