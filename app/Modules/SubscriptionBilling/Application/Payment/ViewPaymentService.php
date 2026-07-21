<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentNotFoundException;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentData;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;

final readonly class ViewPaymentService
{
    public function __construct(
        private PaymentRepositoryInterface $payments,
        private PaymentDataAssembler $data,
    ) {}

    public function execute(string $paymentId): PaymentData
    {
        $payment = $this->payments->find(new PaymentId($paymentId));

        if ($payment === null) {
            throw new PaymentNotFoundException('Payment was not found.');
        }

        return $this->data->fromDomain($payment);
    }
}
