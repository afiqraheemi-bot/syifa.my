<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Authorization;

final readonly class CommercialCatalogueAuthorizationDecision
{
    public function __construct(
        public bool $allowed,
        public ?string $actorPlatformIdentityId = null,
    ) {}
}
