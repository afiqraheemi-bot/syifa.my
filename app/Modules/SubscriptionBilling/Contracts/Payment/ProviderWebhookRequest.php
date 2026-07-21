<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use DateTimeImmutable;

final readonly class ProviderWebhookRequest
{
    /** @param array<string, string|list<string>> $headers */
    public function __construct(
        public string $providerKey,
        public string $rawBody,
        public array $headers,
        public DateTimeImmutable $receivedAt,
        public string $correlationId,
    ) {}
}
