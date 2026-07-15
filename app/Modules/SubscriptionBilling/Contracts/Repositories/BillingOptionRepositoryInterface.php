<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Repositories;

use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\BillingOption;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionId;

interface BillingOptionRepositoryInterface
{
    public function findById(BillingOptionId $billingOptionId): ?BillingOption;

    public function findByCode(BillingOptionCode $billingOptionCode): ?BillingOption;

    public function save(BillingOption $billingOption): void;

    public function existsByCode(BillingOptionCode $billingOptionCode): bool;
}
