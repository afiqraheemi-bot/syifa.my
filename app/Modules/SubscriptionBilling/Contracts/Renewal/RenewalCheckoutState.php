<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

final readonly class RenewalCheckoutState
{
    public function __construct(
        public string $applicationId,
        public string $renewalId,
        public string $paymentId,
        public string $stage,
        public ?PaymentSession $session = null,
        public ?string $safeFailureCode = null,
    ) {}
}
