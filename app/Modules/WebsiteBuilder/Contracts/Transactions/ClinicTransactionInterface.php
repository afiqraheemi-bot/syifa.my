<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Transactions;

interface ClinicTransactionInterface
{
    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function run(callable $operation): mixed;
}
