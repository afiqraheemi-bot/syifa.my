<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Application;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivatedIntegrationEvent;

final readonly class RegisterProvisioningWorkflowService
{
    public function __construct(private ProvisioningWorkflowRepositoryInterface $workflows) {}

    public function execute(SubscriptionActivatedIntegrationEvent $event): ProvisioningWorkflowData
    {
        return $this->workflows->register($event);
    }
}
