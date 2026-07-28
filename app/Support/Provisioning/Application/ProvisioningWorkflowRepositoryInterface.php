<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Application;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivatedIntegrationEvent;
use DateTimeImmutable;

interface ProvisioningWorkflowRepositoryInterface
{
    public function register(SubscriptionActivatedIntegrationEvent $event): ProvisioningWorkflowData;

    public function findBySourceEvent(string $sourceEventId): ?ProvisioningWorkflowData;

    public function claimNext(DateTimeImmutable $now): ?ClaimedProvisioningWorkflow;

    public function advance(string $workflowId, string $claimToken, string $nextStep, DateTimeImmutable $now): bool;

    public function releaseForRetry(
        string $workflowId,
        string $claimToken,
        DateTimeImmutable $retryAt,
        string $safeFailureLabel,
        DateTimeImmutable $now,
    ): bool;

    public function complete(string $workflowId, string $claimToken, DateTimeImmutable $now): bool;
}
