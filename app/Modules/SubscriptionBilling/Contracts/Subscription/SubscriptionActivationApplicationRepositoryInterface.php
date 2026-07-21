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
}
