<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Subscription;

use App\Modules\SubscriptionBilling\Application\Subscription\ActivateSubscriptionFromVerifiedPaymentService;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentIntegrationOutboxEvent;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationJobDispatcherInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class HandleVerifiedPaymentSucceededForSubscriptionActivation
{
    public function __construct(
        private ConnectionInterface $connection,
        private ActivateSubscriptionFromVerifiedPaymentService $activation,
        private SubscriptionActivationJobDispatcherInterface $dispatcher,
    ) {}

    public function handle(PaymentIntegrationOutboxEvent $event): void
    {
        if ($event->type !== 'VerifiedPaymentSucceeded' || $event->eventVersion !== 1) {
            return;
        }

        // Renewal payments (payments/subscription_renewals.payment_id already set) are
        // handled exclusively by ApplyRenewalPaymentOutcomeListener against the existing
        // Subscription. Registering an activation application for one too would find the
        // tenant's Subscription in a RenewalDue state and needlessly open a reconciliation
        // case on every legitimate renewal.
        $isRenewalPayment = $this->connection->table('subscription_renewals')
            ->where('payment_id', $event->paymentId)
            ->exists();
        if ($isRenewalPayment) {
            return;
        }

        $tenantId = $this->connection->table('payments')->where('id', $event->paymentId)->value('tenant_id');
        if (! is_string($tenantId) || $tenantId === '') {
            return;
        }

        $application = $this->activation->register($event->id, $event->paymentId, $tenantId, $event->occurredAt);
        $this->dispatcher->dispatch($application->id);
    }
}
