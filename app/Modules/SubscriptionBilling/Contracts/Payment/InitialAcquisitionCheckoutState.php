<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSession;

final readonly class InitialAcquisitionCheckoutState
{
    public function __construct(
        public string $applicationId,
        public string $clinicRegistrationReference,
        public string $commercialOfferReference,
        public string $paymentId,
        public string $stage,
        public ?PaymentSession $session = null,
    ) {}
}
