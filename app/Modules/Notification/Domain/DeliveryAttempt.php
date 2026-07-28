<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain;

use DateTimeImmutable;
use DomainException;

final readonly class DeliveryAttempt
{
    public function __construct(
        public int $sequence,
        public DateTimeImmutable $attemptedAt,
        public string $outcome,
        public bool $retryEligible,
        public ?string $reasonCode,
    ) {
        if ($sequence < 1) {
            throw new DomainException('A delivery attempt sequence must be positive.');
        }

        if (! in_array($outcome, ['accepted', 'delivered', 'temporary_failure', 'permanent_failure'], true)) {
            throw new DomainException('The delivery attempt outcome is invalid.');
        }
    }
}
