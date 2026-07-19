<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Requests;

use App\Modules\SubscriptionBilling\Presentation\Http\Support\CommercialCatalogueMutationRequest;

final class StorePlanRequest extends CommercialCatalogueMutationRequest
{
    /**
     * @return array<string, list<string>>
     */
    protected function mutationRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1000'],
            'display_order' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function mutationFields(): array
    {
        return ['code', 'name', 'description', 'display_order'];
    }
}
