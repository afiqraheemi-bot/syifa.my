<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Payment;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\IdempotencyKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\ProviderReference;

interface PaymentRepositoryInterface
{
    public function find(PaymentId $paymentId): ?Payment;

    public function findByIdempotencyKey(IdempotencyKey $idempotencyKey): ?Payment;

    public function findByProviderReference(ProviderReference $providerReference): ?Payment;

    public function save(Payment $payment): void;
}
