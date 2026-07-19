<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Controllers;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetPlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GrandfatherPlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListPlanOfferingsService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\MakePlanOfferingUnavailableService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\RetirePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdatePlanOfferingService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\GrandfatherPlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\MakePlanOfferingUnavailableCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\RetirePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Presentation\Http\Collections\PlanOfferingCollection;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\ActivatePlanOfferingRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\GrandfatherPlanOfferingRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\IndexPlanOfferingsRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\MakePlanOfferingUnavailableRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\RetirePlanOfferingRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\StorePlanOfferingRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\UpdatePlanOfferingRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Resources\PlanOfferingResource;
use App\Modules\SubscriptionBilling\Presentation\Http\Support\CommercialCatalogueControllerSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class CommercialCataloguePlanOfferingController extends CommercialCatalogueControllerSupport
{
    public function index(IndexPlanOfferingsRequest $request): JsonResponse
    {
        if (! $this->authorize('commercial_catalogue.plan_offerings.list')) {
            return $this->deny($request);
        }

        $page = app(ListPlanOfferingsService::class)->execute($request->paginationInput());
        $resources = array_map(
            static fn (object $planOffering): PlanOfferingResource => new PlanOfferingResource($planOffering),
            $page->items,
        );

        return $this->collection(
            $request,
            PlanOfferingCollection::fromPagination($request->path(), $resources, $page->meta),
        );
    }

    public function show(Request $request, string $planOfferingId): JsonResponse
    {
        if (! $this->authorize('commercial_catalogue.plan_offerings.view')) {
            return $this->deny($request);
        }

        $planOffering = app(GetPlanOfferingService::class)->execute($planOfferingId);

        if ($planOffering === null) {
            return $this->problem(
                $request,
                new CommercialCatalogueResourceNotFoundException('Plan Offering', $planOfferingId),
            );
        }

        return $this->resource($request, new PlanOfferingResource($planOffering));
    }

    public function store(StorePlanOfferingRequest $request): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.plan_offerings.create'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $planOffering = app(CreatePlanOfferingService::class)->execute(new CreatePlanOfferingCommand(
            $validated['plan_id'],
            $validated['billing_option_id'],
            $validated['amount_minor'],
            $validated['currency_code'],
            $validated['effective_start'],
            $validated['effective_end'] ?? null,
            $validated['capability_configuration_reference'],
            $validated['display_order'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new PlanOfferingResource($planOffering), 201);
    }

    public function update(UpdatePlanOfferingRequest $request, string $planOfferingId): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.plan_offerings.update'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $planOffering = app(UpdatePlanOfferingService::class)->execute(new UpdatePlanOfferingCommand(
            $planOfferingId,
            $validated['amount_minor'],
            $validated['currency_code'],
            $validated['effective_start'],
            $validated['effective_end'] ?? null,
            $validated['capability_configuration_reference'],
            $validated['display_order'],
            $validated['expected_version'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new PlanOfferingResource($planOffering));
    }

    public function activate(ActivatePlanOfferingRequest $request, string $planOfferingId): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.plan_offerings.activate'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $planOffering = app(ActivatePlanOfferingService::class)->execute(new ActivatePlanOfferingCommand(
            $planOfferingId,
            $validated['expected_version'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new PlanOfferingResource($planOffering));
    }

    public function unavailable(MakePlanOfferingUnavailableRequest $request, string $planOfferingId): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.plan_offerings.unavailable'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $planOffering = app(MakePlanOfferingUnavailableService::class)->execute(new MakePlanOfferingUnavailableCommand(
            $planOfferingId,
            $validated['expected_version'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new PlanOfferingResource($planOffering));
    }

    public function grandfather(GrandfatherPlanOfferingRequest $request, string $planOfferingId): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.plan_offerings.grandfather'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $planOffering = app(GrandfatherPlanOfferingService::class)->execute(new GrandfatherPlanOfferingCommand(
            $planOfferingId,
            $validated['expected_version'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new PlanOfferingResource($planOffering));
    }

    public function retire(RetirePlanOfferingRequest $request, string $planOfferingId): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.plan_offerings.retire'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $planOffering = app(RetirePlanOfferingService::class)->execute(new RetirePlanOfferingCommand(
            $planOfferingId,
            $validated['expected_version'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new PlanOfferingResource($planOffering));
    }
}
