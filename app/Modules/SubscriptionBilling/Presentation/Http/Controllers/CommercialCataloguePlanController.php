<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Controllers;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivatePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetPlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GrandfatherPlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListPlansService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\MakePlanUnavailableService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\RetirePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdatePlanDetailsService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\GrandfatherPlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\MakePlanUnavailableCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\RetirePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdatePlanDetailsCommand;
use App\Modules\SubscriptionBilling\Presentation\Http\Collections\PlanCollection;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\ActivatePlanRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\GrandfatherPlanRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\IndexPlansRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\MakePlanUnavailableRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\RetirePlanRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\StorePlanRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\UpdatePlanRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Resources\PlanResource;
use App\Modules\SubscriptionBilling\Presentation\Http\Support\CommercialCatalogueControllerSupport;
use App\Modules\SubscriptionBilling\Presentation\Resources\BaseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class CommercialCataloguePlanController extends CommercialCatalogueControllerSupport
{
    public function index(IndexPlansRequest $request): JsonResponse
    {
        if ($decision = $this->authorize('commercial_catalogue.plans.list')) {
            $page = app(ListPlansService::class)->execute($request->paginationInput());
            $resources = array_map(
                static fn (object $plan): BaseResource => new PlanResource($plan),
                $page->items,
            );

            return $this->collection(
                $request,
                PlanCollection::fromPagination($request->path(), $resources, $page->meta),
            );
        }

        return $this->deny($request);
    }

    public function show(Request $request, string $planId): JsonResponse
    {
        if (! $this->authorize('commercial_catalogue.plans.view')) {
            return $this->deny($request);
        }

        $plan = app(GetPlanService::class)->execute($planId);

        if ($plan === null) {
            return $this->problem($request, new CommercialCatalogueResourceNotFoundException('Plan', $planId));
        }

        return $this->resource($request, new PlanResource($plan));
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.plans.create'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $plan = app(CreatePlanService::class)->execute(new CreatePlanCommand(
            $validated['code'],
            $validated['name'],
            $validated['description'],
            $validated['display_order'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new PlanResource($plan), 201);
    }

    public function update(UpdatePlanRequest $request, string $planId): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.plans.update'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $plan = app(UpdatePlanDetailsService::class)->execute(new UpdatePlanDetailsCommand(
            $planId,
            $validated['name'],
            $validated['description'],
            $validated['display_order'],
            $validated['expected_version'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new PlanResource($plan));
    }

    public function activate(ActivatePlanRequest $request, string $planId): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.plans.activate'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $plan = app(ActivatePlanService::class)->execute(new ActivatePlanCommand(
            $planId,
            $validated['expected_version'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new PlanResource($plan));
    }

    public function unavailable(MakePlanUnavailableRequest $request, string $planId): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.plans.unavailable'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $plan = app(MakePlanUnavailableService::class)->execute(new MakePlanUnavailableCommand(
            $planId,
            $validated['expected_version'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new PlanResource($plan));
    }

    public function grandfather(GrandfatherPlanRequest $request, string $planId): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.plans.grandfather'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $plan = app(GrandfatherPlanService::class)->execute(new GrandfatherPlanCommand(
            $planId,
            $validated['expected_version'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new PlanResource($plan));
    }

    public function retire(RetirePlanRequest $request, string $planId): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.plans.retire'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $plan = app(RetirePlanService::class)->execute(new RetirePlanCommand(
            $planId,
            $validated['expected_version'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new PlanResource($plan));
    }
}
