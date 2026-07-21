<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment;

use Closure;

final readonly class ProviderVerificationRetryPolicy
{
    public function __construct(
        public int $leaseSeconds = 300,
        public int $transportMaxAttempts = 8,
        public int $malformedMaxAttempts = 2,
        public int $baseDelaySeconds = 30,
        public int $maxDelaySeconds = 1800,
        public int $maxRetryAfterSeconds = 21600,
        /** @var null|Closure(int): int */
        private ?Closure $jitter = null,
    ) {}

    public function delaySeconds(int $attempt, ?int $retryAfterSeconds = null): int
    {
        if ($retryAfterSeconds !== null) {
            return min(max(0, $retryAfterSeconds), $this->maxRetryAfterSeconds);
        }

        $base = min($this->maxDelaySeconds, $this->baseDelaySeconds * (2 ** max(0, $attempt - 1)));

        $maximumJitter = (int) floor($base * 0.2);
        $jitter = $this->jitter === null ? random_int(0, $maximumJitter) : ($this->jitter)($maximumJitter);

        return min($this->maxDelaySeconds, $base + max(0, min($maximumJitter, $jitter)));
    }
}
