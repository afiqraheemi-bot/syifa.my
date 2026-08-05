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

    /**
     * Terminally stops a workflow that has exhausted its retry budget. The
     * workflow leaves the claim/lease cycle for good — it is never returned
     * by {@see claimNext()} again — so it must be surfaced to an operator
     * through another channel rather than silently retried forever.
     */
    public function deadLetter(
        string $workflowId,
        string $claimToken,
        string $safeFailureLabel,
        DateTimeImmutable $now,
    ): bool;

    public function complete(string $workflowId, string $claimToken, DateTimeImmutable $now): bool;
}
