<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentData;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Payment;
use DateTimeInterface;

final class PaymentDataAssembler
{
    public function fromDomain(Payment $payment): PaymentData
    {
        return new PaymentData(
            paymentId: $payment->id->value,
            commercialOfferId: $payment->commercialOfferId->value,
            clinicRegistrationId: $payment->clinicRegistrationId->value,
            platformIdentityId: $payment->platformIdentityId?->value,
            tenantId: $payment->tenantId?->value,
            amountMinor: $payment->amount->minorUnits,
            currency: $payment->currency->value,
            idempotencyKey: $payment->idempotencyKey->value,
            status: $payment->status->value,
            providerKey: $payment->providerReference?->providerKey,
            providerPaymentReference: $payment->providerReference?->providerPaymentReference,
            failureReasonCode: $payment->failureReasonCode,
            createdAt: $payment->createdAt->format(DateTimeInterface::ATOM),
            lastChangedAt: $payment->lastChangedAt->format(DateTimeInterface::ATOM),
            version: $payment->version(),
        );
    }
}
