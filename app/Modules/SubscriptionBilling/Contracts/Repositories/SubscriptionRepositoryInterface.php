<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Repositories;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Subscription;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\SubscriptionId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\TenantId;

interface SubscriptionRepositoryInterface
{
    public function find(SubscriptionId $id): ?Subscription;

    public function findByTenantId(TenantId $tenantId): ?Subscription;

    public function findByPaymentId(PaymentId $paymentId): ?Subscription;

    public function save(Subscription $subscription): void;
}
