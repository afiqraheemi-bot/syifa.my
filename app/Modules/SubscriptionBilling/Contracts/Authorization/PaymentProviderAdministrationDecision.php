<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Authorization;

final readonly class PaymentProviderAdministrationDecision
{
    public function __construct(
        public bool $allowed,
        public ?string $platformIdentityId = null,
    ) {}
}
