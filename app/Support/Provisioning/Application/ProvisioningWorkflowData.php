<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Application;

use DateTimeImmutable;

final readonly class ProvisioningWorkflowData
{
    public function __construct(
        public string $id,
        public string $sourceEventId,
        public string $subscriptionId,
        public string $tenantId,
        public string $clinicRegistrationId,
        public string $status,
        public string $currentStep,
        public int $attemptCount,
        public DateTimeImmutable $occurredAt,
    ) {}
}
