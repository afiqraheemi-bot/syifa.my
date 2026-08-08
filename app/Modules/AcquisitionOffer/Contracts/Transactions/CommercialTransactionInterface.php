<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Contracts\Transactions;

interface CommercialTransactionInterface
{
    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function run(callable $operation): mixed;
}
