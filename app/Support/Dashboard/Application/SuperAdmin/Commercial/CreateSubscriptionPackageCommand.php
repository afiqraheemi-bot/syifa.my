<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Commercial;

final readonly class CreateSubscriptionPackageCommand
{
    public function __construct(
        public string $code,
        public string $name,
        public string $description,
        public string $billingOptionId,
        public int $amountMinor,
        public string $effectiveStart,
        public ?string $effectiveEnd,
        public string $occurredAt,
        public string $actorPlatformIdentityId,
        public string $correlationId,
    ) {}
}
