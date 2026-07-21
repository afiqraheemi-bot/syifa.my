<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentAttemptResolverInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ResolvedPaymentAttempt;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final readonly class PostgresPaymentAttemptResolver implements PaymentAttemptResolverInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function resolve(string $providerKey, string $providerPaymentReference): ?ResolvedPaymentAttempt
    {
        $row = $this->connection->table('payment_attempts as a')
            ->join('payments as p', 'p.id', '=', 'a.payment_id')
            ->where('a.provider_key', $providerKey)
            ->where('a.provider_payment_reference', $providerPaymentReference)
            ->select(['a.payment_id', 'a.attempt_reference', 'a.provider_key', 'a.provider_payment_reference', 'a.position', 'p.amount_minor', 'p.currency'])
            ->first();

        if (! $row instanceof stdClass) {
            return null;
        }

        $lastPosition = (int) $this->connection->table('payment_attempts')->where('payment_id', $row->payment_id)->max('position');

        return new ResolvedPaymentAttempt(
            (string) $row->payment_id,
            (string) $row->attempt_reference,
            (string) $row->provider_key,
            (string) $row->provider_payment_reference,
            (int) $row->amount_minor,
            (string) $row->currency,
            (int) $row->position,
            (int) $row->position === $lastPosition,
        );
    }
}
