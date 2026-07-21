<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Payment;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Events\PaymentCancelled;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Events\PaymentCreated;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Events\PaymentExpired;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Events\PaymentFailed;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Events\PaymentPending;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Events\PaymentSucceeded;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Exceptions\InvalidPaymentStateTransitionException;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\IdempotencyKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentAmount;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentCurrency;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentReference;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentStatus;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\ProviderReference;
use DateTimeImmutable;

final class Payment
{
    /**
     * @param  list<PaymentAttempt>  $attempts
     * @param  list<object>  $recordedEvents
     */
    public function __construct(
        public PaymentId $id,
        public PaymentReference $commercialOfferId,
        public PaymentReference $clinicRegistrationId,
        public PaymentReference $platformIdentityId,
        public PaymentAmount $amount,
        public PaymentCurrency $currency,
        public IdempotencyKey $idempotencyKey,
        public PaymentStatus $status,
        public ?ProviderReference $providerReference,
        public ?string $failureReasonCode,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $lastChangedAt,
        public array $attempts = [],
        private int $version = 0,
        private array $recordedEvents = [],
    ) {}

    public static function create(
        PaymentId $id,
        PaymentReference $commercialOfferId,
        PaymentReference $clinicRegistrationId,
        PaymentReference $platformIdentityId,
        PaymentAmount $amount,
        PaymentCurrency $currency,
        IdempotencyKey $idempotencyKey,
        DateTimeImmutable $occurredAt,
    ): self {
        $payment = new self(
            id: $id,
            commercialOfferId: $commercialOfferId,
            clinicRegistrationId: $clinicRegistrationId,
            platformIdentityId: $platformIdentityId,
            amount: $amount,
            currency: $currency,
            idempotencyKey: $idempotencyKey,
            status: PaymentStatus::Draft,
            providerReference: null,
            failureReasonCode: null,
            createdAt: $occurredAt,
            lastChangedAt: $occurredAt,
        );
        $payment->record(new PaymentCreated($id->value, $commercialOfferId->value, $occurredAt));

        return $payment;
    }

    public function start(string $attemptReference, string $providerKey, DateTimeImmutable $occurredAt): void
    {
        $this->assertNotTerminal();

        if (! in_array($this->status, [PaymentStatus::Draft, PaymentStatus::Failed], true)) {
            throw new InvalidPaymentStateTransitionException('Payment may only be started from draft or failed state.');
        }

        $this->attempts[] = PaymentAttempt::start($attemptReference, $providerKey, $occurredAt);
        $this->lastChangedAt = $occurredAt;
    }

    public function markPending(ProviderReference $providerReference, DateTimeImmutable $occurredAt): void
    {
        $this->assertTransitionAllowed([PaymentStatus::Draft, PaymentStatus::Failed], 'Payment may only become pending from draft or failed state.');

        $this->status = PaymentStatus::Pending;
        $this->providerReference = $providerReference;
        $this->failureReasonCode = null;
        $this->lastChangedAt = $occurredAt;
        $this->currentAttempt()?->markPending($providerReference, $occurredAt);
        $this->record(new PaymentPending($this->id->value, $providerReference->providerKey, $providerReference->providerPaymentReference, $occurredAt));
    }

    public function markSucceeded(DateTimeImmutable $occurredAt): void
    {
        $this->assertTransitionAllowed([PaymentStatus::Pending, PaymentStatus::ActionRequired], 'Payment may only succeed from pending or action required state.');

        $this->status = PaymentStatus::Succeeded;
        $this->failureReasonCode = null;
        $this->lastChangedAt = $occurredAt;
        $this->currentAttempt()?->markSucceeded($occurredAt);
        $this->record(new PaymentSucceeded($this->id->value, $occurredAt));
    }

    public function markActionRequired(DateTimeImmutable $occurredAt): void
    {
        $this->assertTransitionAllowed([PaymentStatus::Pending], 'Payment may only require action from pending state.');
        $this->status = PaymentStatus::ActionRequired;
        $this->lastChangedAt = $occurredAt;
        $this->currentAttempt()?->requireAction($occurredAt);
    }

    public function resumePending(DateTimeImmutable $occurredAt): void
    {
        $this->assertTransitionAllowed([PaymentStatus::ActionRequired], 'Payment may only resume pending from action required state.');
        $this->status = PaymentStatus::Pending;
        $this->lastChangedAt = $occurredAt;
        $this->currentAttempt()?->resumePending($occurredAt);
    }

    public function markFailed(string $reasonCode, DateTimeImmutable $occurredAt): void
    {
        $this->assertTransitionAllowed([PaymentStatus::Draft, PaymentStatus::Pending, PaymentStatus::ActionRequired], 'Payment may only fail from draft, pending, or action required state.');

        $this->status = PaymentStatus::Failed;
        $this->failureReasonCode = $reasonCode;
        $this->lastChangedAt = $occurredAt;
        $this->currentAttempt()?->markFailed($reasonCode, $occurredAt);
        $this->record(new PaymentFailed($this->id->value, $reasonCode, $occurredAt));
    }

    public function cancel(DateTimeImmutable $occurredAt): void
    {
        $this->assertTransitionAllowed([PaymentStatus::Draft, PaymentStatus::Pending, PaymentStatus::ActionRequired, PaymentStatus::Failed], 'Payment may not be cancelled from its current state.');

        $this->status = PaymentStatus::Cancelled;
        $this->lastChangedAt = $occurredAt;
        $this->currentAttempt()?->cancel($occurredAt);
        $this->record(new PaymentCancelled($this->id->value, $occurredAt));
    }

    public function expire(DateTimeImmutable $occurredAt): void
    {
        $this->assertTransitionAllowed([PaymentStatus::Draft, PaymentStatus::Pending, PaymentStatus::ActionRequired], 'Payment may not expire from its current state.');

        $this->status = PaymentStatus::Expired;
        $this->lastChangedAt = $occurredAt;
        $this->currentAttempt()?->expire($occurredAt);
        $this->record(new PaymentExpired($this->id->value, $occurredAt));
    }

    public function version(): int
    {
        return $this->version;
    }

    public function synchronizeVersion(int $version): void
    {
        $this->version = $version;
    }

    /**
     * @return list<object>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    private function assertNotTerminal(): void
    {
        if ($this->status->isTerminal()) {
            throw new InvalidPaymentStateTransitionException('Terminal payments cannot transition.');
        }
    }

    /**
     * @param  list<PaymentStatus>  $allowed
     */
    private function assertTransitionAllowed(array $allowed, string $message): void
    {
        if (! in_array($this->status, $allowed, true)) {
            throw new InvalidPaymentStateTransitionException($message);
        }
    }

    private function currentAttempt(): ?PaymentAttempt
    {
        if ($this->attempts === []) {
            return null;
        }

        return $this->attempts[array_key_last($this->attempts)];
    }

    private function record(object $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
