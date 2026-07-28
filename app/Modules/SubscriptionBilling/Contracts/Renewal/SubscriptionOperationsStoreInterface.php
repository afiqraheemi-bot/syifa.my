<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

use App\Modules\Commercial\Contracts\Renewal\PreparedRenewalOffer;

interface SubscriptionOperationsStoreInterface
{
    public function createRenewal(
        string $renewalId,
        ManualRenewSubscriptionCommand $command,
        PreparedRenewalOffer $offer,
    ): RenewalOperationResult;

    public function changeAutoRenew(
        AutoRenewCommand $command,
        string $status,
        string $eventType,
    ): AutoRenewOperationResult;
}
