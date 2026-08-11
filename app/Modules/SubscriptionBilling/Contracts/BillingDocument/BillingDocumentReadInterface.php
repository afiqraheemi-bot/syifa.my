<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\BillingDocument;

interface BillingDocumentReadInterface
{
    /** @return list<BillingDocumentData> */
    public function listForTenant(string $trustedTenantId): array;

    /** @return list<BillingDocumentData> */
    public function listForSubscription(string $subscriptionId): array;

    public function detail(string $paymentId): ?BillingDocumentData;

    public function detailForTenant(string $paymentId, string $trustedTenantId): ?BillingDocumentData;
}
