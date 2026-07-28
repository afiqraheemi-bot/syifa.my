<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Subscription;

use App\Modules\SubscriptionBilling\Contracts\Renewal\BeginRenewalCheckoutCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ClinicOwnerRenewalCheckoutCommandFactoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCheckoutCommandFactoryInterface;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final readonly class PostgresRenewalCheckoutCommandFactory implements ClinicOwnerRenewalCheckoutCommandFactoryInterface, RenewalCheckoutCommandFactoryInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function forRenewal(string $renewalId, string $correlationId): ?BeginRenewalCheckoutCommand
    {
        return $this->command(
            $this->connection->table('subscription_renewals as renewal')
                ->join('commercial_offers as offer', 'offer.id', '=', 'renewal.commercial_offer_id')
                ->where('renewal.id', $renewalId)
                ->select(['renewal.id', 'renewal.payment_id', 'renewal.request_idempotency_key', 'offer.expires_at'])
                ->first(),
            $correlationId,
        );
    }

    public function forTenant(string $trustedTenantId, string $correlationId): ?BeginRenewalCheckoutCommand
    {
        $row = $this->connection->table('subscription_renewals as renewal')
            ->join('subscriptions as subscription', 'subscription.id', '=', 'renewal.subscription_id')
            ->join('commercial_offers as offer', 'offer.id', '=', 'renewal.commercial_offer_id')
            ->where('subscription.tenant_id', $trustedTenantId)
            ->where('subscription.status', 'renewal_due')
            ->where('renewal.status', 'requested')
            ->whereNotNull('renewal.payment_id')
            ->where('offer.expires_at', '>', now())
            ->orderByDesc('renewal.requested_at')
            ->select(['renewal.id', 'renewal.payment_id', 'renewal.request_idempotency_key', 'offer.expires_at'])
            ->first();

        return $this->command($row, $correlationId);
    }

    private function command(mixed $row, string $correlationId): ?BeginRenewalCheckoutCommand
    {
        if (! $row instanceof stdClass || ! is_string($row->payment_id) || ! is_string($row->expires_at)) {
            return null;
        }

        return new BeginRenewalCheckoutCommand(
            (string) $row->id,
            $row->payment_id,
            new DateTimeImmutable($row->expires_at),
            'renewal-checkout:'.(string) $row->request_idempotency_key,
            $correlationId,
            new DateTimeImmutable,
        );
    }
}
