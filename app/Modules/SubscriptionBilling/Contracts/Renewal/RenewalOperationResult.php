<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

final readonly class RenewalOperationResult
{
    public function __construct(public string $code, public ?string $renewalId = null) {}
}
