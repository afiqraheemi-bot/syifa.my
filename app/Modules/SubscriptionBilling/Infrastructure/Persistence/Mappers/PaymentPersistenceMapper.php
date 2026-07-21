<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Payment;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\PaymentAttempt;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\IdempotencyKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentAmount;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentCurrency;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentReference;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentStatus;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\ProviderReference;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\PaymentAttemptStorageRecord;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\PaymentStorageRecord;

final class PaymentPersistenceMapper
{
    public function paymentRecord(Payment $payment): PaymentStorageRecord
    {
        return new PaymentStorageRecord(
            id: $payment->id->value,
            commercialOfferId: $payment->commercialOfferId->value,
            clinicRegistrationId: $payment->clinicRegistrationId->value,
            platformIdentityId: $payment->platformIdentityId->value,
            amountMinor: $payment->amount->minorUnits,
            currency: $payment->currency->value,
            idempotencyKey: $payment->idempotencyKey->value,
            status: $payment->status->value,
            providerKey: $payment->providerReference?->providerKey,
            providerPaymentReference: $payment->providerReference?->providerPaymentReference,
            failureReasonCode: $payment->failureReasonCode,
            createdAt: $payment->createdAt,
            lastChangedAt: $payment->lastChangedAt,
            version: $payment->version(),
        );
    }

    /**
     * @return list<PaymentAttemptStorageRecord>
     */
    public function attemptRecords(Payment $payment): array
    {
        return array_map(
            static fn (PaymentAttempt $attempt, int $position): PaymentAttemptStorageRecord => new PaymentAttemptStorageRecord(
                paymentId: $payment->id->value,
                attemptReference: $attempt->attemptReference,
                status: $attempt->status->value,
                providerKey: $attempt->providerKey,
                providerPaymentReference: $attempt->providerReference?->providerPaymentReference,
                failureReasonCode: $attempt->failureReasonCode,
                startedAt: $attempt->startedAt,
                lastChangedAt: $attempt->lastChangedAt,
                position: $position,
            ),
            $payment->attempts,
            array_keys($payment->attempts),
        );
    }

    /**
     * @param  list<PaymentAttemptStorageRecord>  $attempts
     */
    public function toDomain(PaymentStorageRecord $record, array $attempts): Payment
    {
        return new Payment(
            id: new PaymentId($record->id),
            commercialOfferId: new PaymentReference($record->commercialOfferId),
            clinicRegistrationId: new PaymentReference($record->clinicRegistrationId),
            platformIdentityId: new PaymentReference($record->platformIdentityId),
            amount: new PaymentAmount($record->amountMinor),
            currency: new PaymentCurrency($record->currency),
            idempotencyKey: new IdempotencyKey($record->idempotencyKey),
            status: PaymentStatus::from($record->status),
            providerReference: $record->providerKey === null || $record->providerPaymentReference === null
                ? null
                : new ProviderReference($record->providerKey, $record->providerPaymentReference),
            failureReasonCode: $record->failureReasonCode,
            createdAt: $record->createdAt,
            lastChangedAt: $record->lastChangedAt,
            attempts: array_map(
                static fn (PaymentAttemptStorageRecord $attempt): PaymentAttempt => new PaymentAttempt(
                    attemptReference: $attempt->attemptReference,
                    providerKey: $attempt->providerKey,
                    status: PaymentStatus::from($attempt->status),
                    providerReference: $attempt->providerPaymentReference === null
                        ? null
                        : new ProviderReference($attempt->providerKey, $attempt->providerPaymentReference),
                    failureReasonCode: $attempt->failureReasonCode,
                    startedAt: $attempt->startedAt,
                    lastChangedAt: $attempt->lastChangedAt,
                ),
                $attempts,
            ),
            version: $record->version,
        );
    }
}
