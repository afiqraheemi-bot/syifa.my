<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Requests;

use App\Modules\SubscriptionBilling\Presentation\Http\Support\CommercialCatalogueMutationRequest;

final class StorePlanOfferingRequest extends CommercialCatalogueMutationRequest
{
    /**
     * @return array<string, list<string>>
     */
    protected function mutationRules(): array
    {
        return [
            'plan_id' => ['required', 'uuid'],
            'billing_option_id' => ['required', 'uuid'],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'currency_code' => ['required', 'string', 'size:3', 'uppercase'],
            'effective_start' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'effective_end' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'capability_configuration_reference' => ['required', 'string', 'max:100'],
            'display_order' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function mutationFields(): array
    {
        return [
            'plan_id',
            'billing_option_id',
            'amount_minor',
            'currency_code',
            'effective_start',
            'effective_end',
            'capability_configuration_reference',
            'display_order',
        ];
    }
}
