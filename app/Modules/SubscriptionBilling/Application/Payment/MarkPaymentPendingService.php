<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentNotFoundException;
use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentVersionMismatchException;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentAuditInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentData;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentTransitionCommand;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\ProviderReference;

final readonly class MarkPaymentPendingService
{
    public function __construct(
        private PaymentRepositoryInterface $payments,
        private PaymentDataAssembler $data,
        private PaymentAuditInterface $audit,
    ) {}

    public function execute(PaymentTransitionCommand $command): PaymentData
    {
        $payment = $this->payments->find(new PaymentId($command->paymentId));

        if ($payment === null) {
            throw new PaymentNotFoundException('Payment was not found.');
        }

        if ($payment->version() !== $command->expectedVersion) {
            throw new PaymentVersionMismatchException('Payment version does not match.');
        }

        if ($command->providerKey === null || $command->providerPaymentReference === null) {
            throw new PaymentVersionMismatchException('Provider reference is required to mark Payment pending.');
        }

        $payment->markPending(new ProviderReference($command->providerKey, $command->providerPaymentReference), $command->occurredAt);
        $this->payments->save($payment);
        $this->audit->record('payment.pending', $payment, $command->occurredAt, $command->correlationId);

        return $this->data->fromDomain($payment);
    }
}
