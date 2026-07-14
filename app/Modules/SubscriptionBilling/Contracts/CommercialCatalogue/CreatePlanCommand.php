<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue;

final readonly class CreatePlanCommand
{
    /**
     * RFC 3339 UTC instant in the canonical `YYYY-MM-DDTHH:MM:SSZ` format.
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $description,
        public int $displayOrder,
        public string $occurredAt,
        public string $actorPlatformIdentityId,
        public string $correlationId,
    ) {}
}
