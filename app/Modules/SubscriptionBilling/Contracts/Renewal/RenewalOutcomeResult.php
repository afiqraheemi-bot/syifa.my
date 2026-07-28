<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

final readonly class RenewalOutcomeResult
{
    public function __construct(public string $code, public ?string $renewalId = null) {}
}
