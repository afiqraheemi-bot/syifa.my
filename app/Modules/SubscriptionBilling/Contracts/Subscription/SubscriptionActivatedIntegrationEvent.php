<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Subscription;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class SubscriptionActivatedIntegrationEvent
{
    public const int VERSION = 1;

    public const string TYPE = 'SubscriptionActivated';

    public function __construct(
        public string $eventId,
        public string $subscriptionId,
        public string $tenantId,
        public string $clinicRegistrationId,
        public string $paymentId,
        public string $commercialOfferId,
        public string $planId,
        public string $billingCycleId,
        public string $startsOn,
        public string $endsOn,
        public DateTimeImmutable $occurredAt,
        public int $eventVersion = self::VERSION,
    ) {
        foreach ([$eventId, $subscriptionId, $tenantId, $clinicRegistrationId, $paymentId, $commercialOfferId, $planId, $billingCycleId] as $identifier) {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $identifier) !== 1) {
                throw new InvalidArgumentException('Subscription integration event identifiers must be UUIDs.');
            }
        }
        if ($eventVersion !== self::VERSION) {
            throw new InvalidArgumentException('Unsupported SubscriptionActivated event version.');
        }
    }

    /** @return array<string, int|string> */
    public function payload(): array
    {
        return [
            'event_id' => $this->eventId, 'event_version' => $this->eventVersion,
            'subscription_id' => $this->subscriptionId, 'tenant_id' => $this->tenantId,
            'clinic_registration_id' => $this->clinicRegistrationId, 'payment_id' => $this->paymentId,
            'commercial_offer_id' => $this->commercialOfferId, 'plan_id' => $this->planId,
            'billing_cycle_id' => $this->billingCycleId, 'starts_on' => $this->startsOn,
            'ends_on' => $this->endsOn, 'occurred_at' => $this->occurredAt->format('Y-m-d\TH:i:s.uP'),
        ];
    }
}
