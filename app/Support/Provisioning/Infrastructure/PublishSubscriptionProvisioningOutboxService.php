<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Infrastructure;

use App\Modules\Notification\Contracts\TransactionalNotificationGatewayInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionIntegrationOutboxRepositoryInterface;
use App\Support\Provisioning\Application\RegisterProvisioningWorkflowService;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final readonly class PublishSubscriptionProvisioningOutboxService
{
    private const string RETRY_FAILURE_LABEL = 'provisioning_workflow_registration_failed';

    public function __construct(
        private SubscriptionIntegrationOutboxRepositoryInterface $outbox,
        private RegisterProvisioningWorkflowService $workflows,
        private ?TransactionalNotificationGatewayInterface $notifications = null,
    ) {}

    public function publishNext(): bool
    {
        $now = new DateTimeImmutable;
        $claim = $this->outbox->claimNext($now);
        if ($claim === null) {
            return false;
        }

        try {
            $this->workflows->execute($claim->event);
        } catch (Throwable) {
            $released = $this->outbox->releaseForRetry(
                $claim->event->eventId,
                $claim->leaseToken,
                $now->modify('+30 seconds'),
                self::RETRY_FAILURE_LABEL,
                $now,
            );
            if (! $released) {
                throw new RuntimeException('Subscription provisioning outbox retry lease became stale.');
            }

            return true;
        }

        if (! $this->outbox->completeDispatch($claim->event->eventId, $claim->leaseToken, $now)) {
            throw new RuntimeException('Subscription provisioning outbox completion lease became stale.');
        }
        $this->notifications?->subscriptionActivated(
            $claim->event->tenantId,
            $claim->event->subscriptionId,
            $claim->event->clinicRegistrationId,
        );

        return true;
    }
}
