<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Support;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;

abstract class CommercialCatalogueIndexRequest extends CommercialCatalogueFormRequest
{
    /**
     * @return array<string, list<string>>
     */
    final public function rules(): array
    {
        return [
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
            'correlation_id' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return list<string>
     */
    final protected function allowedFields(): array
    {
        return ['page', 'per_page', 'correlation_id'];
    }

    public function paginationInput(): OffsetPaginationInput
    {
        /** @var array{page: int|string, per_page: int|string} $validated */
        $validated = $this->validated();

        return new OffsetPaginationInput((int) $validated['page'], (int) $validated['per_page']);
    }
}
