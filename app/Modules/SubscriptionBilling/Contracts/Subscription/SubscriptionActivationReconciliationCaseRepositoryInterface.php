<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Subscription;

use DateTimeImmutable;

interface SubscriptionActivationReconciliationCaseRepositoryInterface
{
    public function open(string $applicationId, string $paymentId, string $tenantId, string $reasonCode, DateTimeImmutable $openedAt): string;
}
