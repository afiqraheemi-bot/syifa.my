<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Infrastructure\ReferenceData;

use App\Modules\Commercial\Contracts\ReferenceData\BillingCycleQueryInterface;
use App\Modules\Commercial\Contracts\ReferenceData\BillingCycleReferenceData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;

final readonly class SubscriptionBillingBillingCycleQueryAdapter implements BillingCycleQueryInterface
{
    public function __construct(private CommercialCatalogueQueryInterface $catalogue) {}

    public function findActiveBillingCycle(string $billingCycleId): ?BillingCycleReferenceData
    {
        $billingOption = $this->catalogue->findBillingOption($billingCycleId);

        if ($billingOption === null || $billingOption->availability !== 'available' || $billingOption->recurrenceClassification !== 'recurring') {
            return null;
        }

        return new BillingCycleReferenceData(
            $billingOption->billingOptionId,
            $billingOption->code,
            $billingOption->name,
            $billingOption->availability,
            $billingOption->recurrenceClassification,
        );
    }
}
