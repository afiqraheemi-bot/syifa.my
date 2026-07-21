<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Subscription;

use DateTimeImmutable;

final readonly class SubscriptionIntegrationOutboxClaim
{
    public function __construct(
        public SubscriptionActivatedIntegrationEvent $event,
        public string $leaseToken,
        public DateTimeImmutable $leaseExpiresAt,
        public int $attemptCount,
    ) {}
}
