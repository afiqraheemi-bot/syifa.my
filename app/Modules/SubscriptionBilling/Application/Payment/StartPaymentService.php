<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentNotFoundException;
use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentVersionMismatchException;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentAuditInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentData;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentTransactionInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentTransitionCommand;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentRequest;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\ProviderReference;

final readonly class StartPaymentService
{
    public function __construct(
        private PaymentIdentifierGeneratorInterface $identifiers,
        private PaymentRepositoryInterface $payments,
        private PaymentProviderInterface $provider,
        private PaymentDataAssembler $data,
        private PaymentAuditInterface $audit,
        private PaymentTransactionInterface $transactions,
    ) {}

    public function execute(PaymentTransitionCommand $command): PaymentData
    {
        return $this->transactions->run(function () use ($command): PaymentData {
            $payment = $this->payments->find(new PaymentId($command->paymentId));

            if ($payment === null) {
                throw new PaymentNotFoundException('Payment was not found.');
            }

            if ($payment->version() !== $command->expectedVersion) {
                throw new PaymentVersionMismatchException('Payment version does not match.');
            }

            $payment->start($this->identifiers->generate(), $command->occurredAt);
            $result = $this->provider->start(new ProviderPaymentRequest(
                paymentId: $payment->id->value,
                amountMinor: $payment->amount->minorUnits,
                currency: $payment->currency->value,
                idempotencyKey: $payment->idempotencyKey->value,
                correlationId: $command->correlationId,
            ));
            $payment->markPending(new ProviderReference($result->providerKey, $result->providerPaymentReference), $command->occurredAt);
            $this->payments->save($payment);
            $this->audit->record('payment.start', $payment, $command->occurredAt, $command->correlationId);
            $this->audit->record('payment.pending', $payment, $command->occurredAt, $command->correlationId);

            return $this->data->fromDomain($payment);
        });
    }
}
