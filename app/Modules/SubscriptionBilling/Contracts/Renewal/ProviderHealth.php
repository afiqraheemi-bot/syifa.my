<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

use DateTimeImmutable;

final readonly class ProviderHealth
{
    public function __construct(
        public string $providerKey,
        public string $status,
        public bool $acceptingNewAttempts,
        public DateTimeImmutable $observedAt,
        public string $safeReasonCode,
    ) {}
}
