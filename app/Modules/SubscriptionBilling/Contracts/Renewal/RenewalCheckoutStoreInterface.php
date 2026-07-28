<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

interface RenewalCheckoutStoreInterface
{
    public function begin(BeginRenewalCheckoutCommand $command): RenewalCheckoutState;

    public function sessionReady(
        string $applicationId,
        string $paymentId,
        PaymentSession $session,
        string $correlationId,
    ): RenewalCheckoutState;

    public function fail(
        string $applicationId,
        string $safeFailureCode,
        string $correlationId,
    ): RenewalCheckoutState;
}
