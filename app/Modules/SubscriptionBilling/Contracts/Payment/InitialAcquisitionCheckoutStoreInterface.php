<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSession;
use DateTimeImmutable;

interface InitialAcquisitionCheckoutStoreInterface
{
    public function begin(
        string $clinicRegistrationReference,
        string $commercialOfferReference,
        string $paymentId,
        DateTimeImmutable $commercialOfferValidUntil,
        DateTimeImmutable $occurredAt,
    ): InitialAcquisitionCheckoutState;

    public function sessionReady(
        string $applicationId,
        string $paymentId,
        PaymentSession $session,
        DateTimeImmutable $occurredAt,
    ): InitialAcquisitionCheckoutState;
}
