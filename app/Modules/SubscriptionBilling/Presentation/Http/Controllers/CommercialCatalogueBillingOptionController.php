<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Controllers;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreateBillingOptionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetBillingOptionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListBillingOptionsService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdateBillingOptionService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateBillingOptionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdateBillingOptionCommand;
use App\Modules\SubscriptionBilling\Presentation\Http\Collections\BillingOptionCollection;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\IndexBillingOptionsRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\StoreBillingOptionRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\UpdateBillingOptionRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Resources\BillingOptionResource;
use App\Modules\SubscriptionBilling\Presentation\Http\Support\CommercialCatalogueControllerSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class CommercialCatalogueBillingOptionController extends CommercialCatalogueControllerSupport
{
    public function index(IndexBillingOptionsRequest $request): JsonResponse
    {
        if (! $this->authorize('commercial_catalogue.billing_options.list')) {
            return $this->deny($request);
        }

        $page = app(ListBillingOptionsService::class)->execute($request->paginationInput());
        $resources = array_map(
            static fn (object $billingOption): BillingOptionResource => new BillingOptionResource($billingOption),
            $page->items,
        );

        return $this->collection(
            $request,
            BillingOptionCollection::fromPagination($request->path(), $resources, $page->meta),
        );
    }

    public function show(Request $request, string $billingOptionId): JsonResponse
    {
        if (! $this->authorize('commercial_catalogue.billing_options.view')) {
            return $this->deny($request);
        }

        $billingOption = app(GetBillingOptionService::class)->execute($billingOptionId);

        if ($billingOption === null) {
            return $this->problem(
                $request,
                new CommercialCatalogueResourceNotFoundException('Billing Option', $billingOptionId),
            );
        }

        return $this->resource($request, new BillingOptionResource($billingOption));
    }

    public function store(StoreBillingOptionRequest $request): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.billing_options.create'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $billingOption = app(CreateBillingOptionService::class)->execute(new CreateBillingOptionCommand(
            $validated['code'],
            $validated['name'],
            $validated['recurrence_classification'],
            $validated['interval_unit'] ?? null,
            $validated['interval_count'] ?? null,
            $validated['display_order'],
            $validated['effective_start'],
            $validated['effective_end'] ?? null,
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new BillingOptionResource($billingOption), 201);
    }

    public function update(UpdateBillingOptionRequest $request, string $billingOptionId): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.billing_options.update'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $billingOption = app(UpdateBillingOptionService::class)->execute(new UpdateBillingOptionCommand(
            $billingOptionId,
            $validated['name'],
            $validated['availability'],
            $validated['recurrence_classification'],
            $validated['interval_unit'] ?? null,
            $validated['interval_count'] ?? null,
            $validated['effective_start'],
            $validated['effective_end'] ?? null,
            $validated['display_order'],
            $validated['expected_version'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new BillingOptionResource($billingOption));
    }
}
