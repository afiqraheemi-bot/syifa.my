<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Requests;

use App\Modules\SubscriptionBilling\Presentation\Http\Support\CommercialCatalogueMutationRequest;

final class UpdateBillingOptionRequest extends CommercialCatalogueMutationRequest
{
    /**
     * @return array<string, list<string>>
     */
    protected function mutationRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'availability' => ['required', 'in:available,unavailable'],
            'recurrence_classification' => ['required', 'in:recurring,non_recurring'],
            'interval_unit' => ['required_if:recurrence_classification,recurring', 'nullable', 'in:month,year', 'prohibited_if:recurrence_classification,non_recurring'],
            'interval_count' => ['required_if:recurrence_classification,recurring', 'nullable', 'integer', 'min:1', 'prohibited_if:recurrence_classification,non_recurring'],
            'effective_start' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'effective_end' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'display_order' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function mutationFields(): array
    {
        return [
            'code',
            'name',
            'availability',
            'recurrence_classification',
            'interval_unit',
            'interval_count',
            'effective_start',
            'effective_end',
            'display_order',
        ];
    }
}
