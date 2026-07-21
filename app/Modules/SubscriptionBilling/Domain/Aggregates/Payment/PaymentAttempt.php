<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Payment;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentStatus;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\ProviderReference;
use DateTimeImmutable;

final class PaymentAttempt
{
    public function __construct(
        public string $attemptReference,
        public PaymentStatus $status,
        public ?ProviderReference $providerReference,
        public ?string $failureReasonCode,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $lastChangedAt,
    ) {}

    public static function start(string $attemptReference, DateTimeImmutable $occurredAt): self
    {
        return new self(
            attemptReference: $attemptReference,
            status: PaymentStatus::Draft,
            providerReference: null,
            failureReasonCode: null,
            startedAt: $occurredAt,
            lastChangedAt: $occurredAt,
        );
    }

    public function markPending(ProviderReference $providerReference, DateTimeImmutable $occurredAt): void
    {
        $this->status = PaymentStatus::Pending;
        $this->providerReference = $providerReference;
        $this->lastChangedAt = $occurredAt;
    }

    public function markSucceeded(DateTimeImmutable $occurredAt): void
    {
        $this->status = PaymentStatus::Succeeded;
        $this->failureReasonCode = null;
        $this->lastChangedAt = $occurredAt;
    }

    public function markFailed(string $reasonCode, DateTimeImmutable $occurredAt): void
    {
        $this->status = PaymentStatus::Failed;
        $this->failureReasonCode = $reasonCode;
        $this->lastChangedAt = $occurredAt;
    }

    public function cancel(DateTimeImmutable $occurredAt): void
    {
        $this->status = PaymentStatus::Cancelled;
        $this->lastChangedAt = $occurredAt;
    }

    public function expire(DateTimeImmutable $occurredAt): void
    {
        $this->status = PaymentStatus::Expired;
        $this->lastChangedAt = $occurredAt;
    }
}
