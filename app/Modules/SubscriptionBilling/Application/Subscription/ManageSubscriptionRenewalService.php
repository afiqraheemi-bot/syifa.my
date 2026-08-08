<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Subscription;

use App\Modules\AcquisitionOffer\Contracts\Renewal\PrepareRenewalOfferInput;
use App\Modules\AcquisitionOffer\Contracts\Renewal\PrepareRenewalOfferInterface;
use App\Modules\AcquisitionOffer\Contracts\Renewal\RenewalUnavailable;
use App\Modules\SubscriptionBilling\Contracts\Renewal\AutoRenewCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\AutoRenewOperationResult;
use App\Modules\SubscriptionBilling\Contracts\Renewal\CancelAutoRenewInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\EnableAutoRenewInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ManualRenewSubscriptionCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ManualRenewSubscriptionInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalOperationResult;
use App\Modules\SubscriptionBilling\Contracts\Renewal\SubscriptionOperationsStoreInterface;
use Illuminate\Support\Str;

final readonly class ManageSubscriptionRenewalService implements CancelAutoRenewInterface, EnableAutoRenewInterface, ManualRenewSubscriptionInterface
{
    public function __construct(
        private PrepareRenewalOfferInterface $offers,
        private SubscriptionOperationsStoreInterface $store,
    ) {}

    public function renew(ManualRenewSubscriptionCommand $command): RenewalOperationResult
    {
        $renewalId = (string) Str::uuid();
        $offer = $this->offers->prepare(new PrepareRenewalOfferInput(
            $command->subscriptionId,
            $renewalId,
            $command->idempotencyKey,
            $command->actorId,
            $command->occurredAt,
            $command->correlationId,
        ));
        if ($offer instanceof RenewalUnavailable) {
            return new RenewalOperationResult($offer->reason);
        }

        return $this->store->createRenewal($renewalId, $command, $offer);
    }

    public function enable(AutoRenewCommand $command): AutoRenewOperationResult
    {
        return $this->store->changeAutoRenew($command, 'enabled', 'auto_renew_enabled');
    }

    public function cancel(AutoRenewCommand $command): AutoRenewOperationResult
    {
        return $this->store->changeAutoRenew($command, 'cancelled', 'auto_renew_cancelled');
    }
}
