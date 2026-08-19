<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Subscription;

use DateTimeImmutable;

interface SubscriptionActivationApplicationRepositoryInterface
{
    public function register(string $sourceEventId, string $paymentId, string $tenantId, DateTimeImmutable $now): SubscriptionActivationApplication;

    public function claim(string $applicationId, DateTimeImmutable $now, int $leaseSeconds): ?SubscriptionActivationApplication;

    public function find(string $applicationId): ?SubscriptionActivationApplication;

    public function complete(string $applicationId, string $claimToken, SubscriptionActivationApplicationStatus $status, SubscriptionActivationApplicationResultCode $resultCode, DateTimeImmutable $now, ?DateTimeImmutable $nextAttemptAt = null): bool;

    /**
     * Unconditionally (not scoped to a specific claim token) transitions the
     * application to Exhausted, but only while it is still in a non-terminal
     * status — a safe no-op once another attempt has already reached a
     * terminal outcome. Called once the queue's own retry budget is spent,
     * so there is no live claim token left to scope against.
     */
    public function markExhausted(string $applicationId, string $safeFailureLabel, DateTimeImmutable $now): bool;
}
