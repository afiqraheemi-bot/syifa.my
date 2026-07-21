<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

final readonly class ProviderWebhookEvent
{
    public function __construct(
        public string $providerKey,
        public string $providerEventId,
        public string $providerPaymentReference,
        public string $eventType,
    ) {}
}
