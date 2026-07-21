<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Subscription;

use DateTimeImmutable;

interface SubscriptionActivationAuditInterface
{
    public function record(string $action, string $applicationId, string $subscriptionId, string $paymentId, string $tenantId, SubscriptionActivationApplicationResultCode $resultCode, DateTimeImmutable $occurredAt): void;
}
