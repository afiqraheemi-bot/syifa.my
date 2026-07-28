<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

interface PaymentSessionCreationInterface
{
    public function create(CreatePaymentSessionInput $input): PaymentSession|PaymentSessionUnavailable;
}
