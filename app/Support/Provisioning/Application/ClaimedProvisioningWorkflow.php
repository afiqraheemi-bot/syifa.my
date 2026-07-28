<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Application;

final readonly class ClaimedProvisioningWorkflow
{
    public function __construct(
        public ProvisioningWorkflowData $workflow,
        public string $claimToken,
    ) {}
}
