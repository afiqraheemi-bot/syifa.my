<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Application;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivatedIntegrationEvent;

interface ProvisioningWorkflowRepositoryInterface
{
    public function register(SubscriptionActivatedIntegrationEvent $event): ProvisioningWorkflowData;

    public function findBySourceEvent(string $sourceEventId): ?ProvisioningWorkflowData;
}
