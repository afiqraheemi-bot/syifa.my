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

final readonly class ExpirePaymentService
{
    public function __construct(private PaymentRepositoryInterface $payments, private PaymentDataAssembler $data, private PaymentAuditInterface $audit) {}

    public function execute(PaymentTransitionCommand $command): PaymentData
    {
        $payment = $this->payments->find(new PaymentId($command->paymentId));

        if ($payment === null) {
            throw new PaymentNotFoundException('Payment was not found.');
        }

        if ($payment->version() !== $command->expectedVersion) {
            throw new PaymentVersionMismatchException('Payment version does not match.');
        }

        $payment->expire($command->occurredAt);
        $this->payments->save($payment);
        $this->audit->record('payment.expire', $payment, $command->occurredAt, $command->correlationId);

        return $this->data->fromDomain($payment);
    }
}
