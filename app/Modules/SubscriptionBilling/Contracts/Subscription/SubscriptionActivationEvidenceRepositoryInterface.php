<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Subscription;

interface SubscriptionActivationEvidenceRepositoryInterface
{
    public function loadForUpdate(string $sourceEventId, string $paymentId): ?SubscriptionActivationEvidence;
}
