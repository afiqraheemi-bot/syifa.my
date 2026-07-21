<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

final readonly class ProviderConfigurationVerification
{
    public function __construct(
        public bool $passed,
        public string $safeReasonCode,
    ) {}
}
