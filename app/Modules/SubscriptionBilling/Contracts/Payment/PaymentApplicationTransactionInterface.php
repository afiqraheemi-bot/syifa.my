<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

interface PaymentApplicationTransactionInterface
{
    public function run(callable $operation): mixed;
}
