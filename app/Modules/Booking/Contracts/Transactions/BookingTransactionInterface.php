<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Transactions;

interface BookingTransactionInterface
{
    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function run(callable $operation): mixed;
}
