<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Review;

use Closure;

interface ClinicRegistrationDecisionTransactionInterface
{
    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    public function run(Closure $operation): mixed;
}
