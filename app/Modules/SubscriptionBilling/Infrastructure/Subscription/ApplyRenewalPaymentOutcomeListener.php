<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Subscription;

use App\Modules\SubscriptionBilling\Application\Subscription\RenewalOutcomeApplication;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentIntegrationOutboxEvent;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ApplyRenewalOutcomeCommand;

final readonly class ApplyRenewalPaymentOutcomeListener
{
    public function __construct(private RenewalOutcomeApplication $application) {}

    public function handle(PaymentIntegrationOutboxEvent $event): void
    {
        $outcome = match ($event->type) {
            'VerifiedPaymentSucceeded' => 'succeeded',
            'VerifiedPaymentFailed' => 'failed',
            'PaymentExpired' => 'expired',
            default => null,
        };
        if ($outcome === null) {
            return;
        }
        $this->application->execute(new ApplyRenewalOutcomeCommand(
            $event->id,
            $event->paymentId,
            $outcome,
            $event->id,
            $event->occurredAt,
        ));
    }
}
