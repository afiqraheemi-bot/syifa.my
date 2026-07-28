<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use App\Modules\SubscriptionBilling\Contracts\Renewal\ExpiryAuthority;
use DateTimeImmutable;

final readonly class ProviderPaymentResult
{
    public function __construct(
        public string $providerKey,
        public string $providerPaymentReference,
        public ?string $redirectDestination = null,
        public ?DateTimeImmutable $expiresAt = null,
        public ExpiryAuthority $expiryAuthority = ExpiryAuthority::None,
    ) {}
}
