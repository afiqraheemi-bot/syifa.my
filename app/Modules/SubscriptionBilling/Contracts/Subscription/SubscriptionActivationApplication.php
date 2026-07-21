<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Subscription;

use DateTimeImmutable;

final readonly class SubscriptionActivationApplication
{
    public function __construct(
        public string $id,
        public string $sourceEventId,
        public string $paymentId,
        public string $subscriptionId,
        public string $tenantId,
        public SubscriptionActivationApplicationStatus $status,
        public int $attemptCount,
        public ?string $claimToken,
        public ?DateTimeImmutable $leaseExpiresAt,
        public ?DateTimeImmutable $nextAttemptAt,
        public ?SubscriptionActivationApplicationResultCode $resultCode,
    ) {}
}
