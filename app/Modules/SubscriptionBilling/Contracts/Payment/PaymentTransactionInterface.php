<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

interface PaymentTransactionInterface
{
    /**
     * @template TResult
     *
     * @param  callable(): TResult  $operation
     * @return TResult
     */
    public function run(callable $operation): mixed;
}
