<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Requests;

use App\Modules\SubscriptionBilling\Presentation\Http\Support\CommercialCatalogueMutationRequest;

final class StoreCapabilityDefinitionRequest extends CommercialCatalogueMutationRequest
{
    /**
     * @return array<string, list<string>>
     */
    protected function mutationRules(): array
    {
        return [
            'capability_key' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1000'],
            'commercial_meaning' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function mutationFields(): array
    {
        return ['capability_key', 'name', 'description', 'commercial_meaning'];
    }
}
