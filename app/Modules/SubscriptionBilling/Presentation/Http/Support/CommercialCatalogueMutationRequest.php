<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Support;

abstract class CommercialCatalogueMutationRequest extends CommercialCatalogueFormRequest
{
    /**
     * @return array<string, list<string>>
     */
    final public function rules(): array
    {
        return array_merge($this->commonRules(), $this->mutationRules());
    }

    /**
     * @return list<string>
     */
    final protected function allowedFields(): array
    {
        return array_merge($this->commonFields(), $this->mutationFields());
    }

    /**
     * @return array<string, list<string>>
     */
    protected function commonRules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'occurred_at' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/'],
            'correlation_id' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function commonFields(): array
    {
        return ['expected_version', 'occurred_at', 'correlation_id'];
    }

    /**
     * @return array<string, list<string>>
     */
    abstract protected function mutationRules(): array;

    /**
     * @return list<string>
     */
    abstract protected function mutationFields(): array;
}
