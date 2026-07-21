<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class SubscriptionActivationApplicationStorageRecord
{
    public function __construct(
        public string $id, public string $sourceEventId, public string $paymentId, public string $subscriptionId,
        public string $tenantId, public string $status, public int $attemptCount, public ?string $claimToken,
        public ?DateTimeImmutable $leaseExpiresAt, public ?DateTimeImmutable $nextAttemptAt, public ?string $resultCode,
    ) {}
}
