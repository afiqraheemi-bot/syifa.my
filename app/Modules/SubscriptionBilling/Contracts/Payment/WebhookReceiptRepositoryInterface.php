<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use DateTimeImmutable;

interface WebhookReceiptRepositoryInterface
{
    public function hasProcessed(string $providerKey, string $providerEventId): bool;

    public function recordProcessed(string $providerKey, string $providerEventId, string $processingStatus, DateTimeImmutable $occurredAt, string $correlationId): void;
}
