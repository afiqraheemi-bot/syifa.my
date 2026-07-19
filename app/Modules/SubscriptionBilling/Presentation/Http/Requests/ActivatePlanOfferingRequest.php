<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Requests;

use App\Modules\SubscriptionBilling\Presentation\Http\Support\CommercialCatalogueMutationRequest;

final class ActivatePlanOfferingRequest extends CommercialCatalogueMutationRequest
{
    /**
     * @return array<string, list<string>>
     */
    protected function mutationRules(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    protected function mutationFields(): array
    {
        return [];
    }
}
