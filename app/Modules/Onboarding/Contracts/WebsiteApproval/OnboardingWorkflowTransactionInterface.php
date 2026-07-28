<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\WebsiteApproval;

interface OnboardingWorkflowTransactionInterface
{
    /** @template TResult
     * @param  callable(): TResult  $operation
     * @return TResult
     */
    public function run(callable $operation): mixed;
}
