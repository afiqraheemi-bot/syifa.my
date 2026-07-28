<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Persistence;

use App\Modules\Onboarding\Contracts\WebsiteApproval\OnboardingWorkflowTransactionInterface;
use Closure;
use Illuminate\Database\ConnectionInterface;

final readonly class OnboardingDatabaseWorkflowTransaction implements OnboardingWorkflowTransactionInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function run(callable $operation): mixed
    {
        return $this->connection->transaction(Closure::fromCallable($operation));
    }
}
