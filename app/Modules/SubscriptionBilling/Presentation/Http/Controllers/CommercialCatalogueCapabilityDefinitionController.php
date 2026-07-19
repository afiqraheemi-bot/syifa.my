<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Controllers;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\DeprecateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListCapabilityDefinitionsService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\RetireCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\DeprecateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\RetireCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Presentation\Http\Collections\CapabilityDefinitionCollection;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\ActivateCapabilityDefinitionRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\DeprecateCapabilityDefinitionRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\IndexCapabilityDefinitionsRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\RetireCapabilityDefinitionRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\StoreCapabilityDefinitionRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Requests\UpdateCapabilityDefinitionRequest;
use App\Modules\SubscriptionBilling\Presentation\Http\Resources\CapabilityDefinitionResource;
use App\Modules\SubscriptionBilling\Presentation\Http\Support\CommercialCatalogueControllerSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class CommercialCatalogueCapabilityDefinitionController extends CommercialCatalogueControllerSupport
{
    public function index(IndexCapabilityDefinitionsRequest $request): JsonResponse
    {
        if (! $this->authorize('commercial_catalogue.capabilities.list')) {
            return $this->deny($request);
        }

        $page = app(ListCapabilityDefinitionsService::class)->execute($request->paginationInput());
        $resources = array_map(
            static fn (object $capability): CapabilityDefinitionResource => new CapabilityDefinitionResource($capability),
            $page->items,
        );

        return $this->collection(
            $request,
            CapabilityDefinitionCollection::fromPagination($request->path(), $resources, $page->meta),
        );
    }

    public function show(Request $request, string $capabilityId): JsonResponse
    {
        if (! $this->authorize('commercial_catalogue.capabilities.view')) {
            return $this->deny($request);
        }

        $capability = app(GetCapabilityDefinitionService::class)->execute($capabilityId);

        if ($capability === null) {
            return $this->problem(
                $request,
                new CommercialCatalogueResourceNotFoundException('Capability Definition', $capabilityId),
            );
        }

        return $this->resource($request, new CapabilityDefinitionResource($capability));
    }

    public function store(StoreCapabilityDefinitionRequest $request): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.capabilities.create'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $capability = app(CreateCapabilityDefinitionService::class)->execute(new CreateCapabilityDefinitionCommand(
            $validated['capability_key'],
            $validated['name'],
            $validated['description'],
            $validated['commercial_meaning'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new CapabilityDefinitionResource($capability), 201);
    }

    public function update(UpdateCapabilityDefinitionRequest $request, string $capabilityId): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.capabilities.update'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $capability = app(UpdateCapabilityDefinitionService::class)->execute(new UpdateCapabilityDefinitionCommand(
            $capabilityId,
            $validated['name'],
            $validated['description'],
            $validated['commercial_meaning'],
            $validated['expected_version'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new CapabilityDefinitionResource($capability));
    }

    public function activate(ActivateCapabilityDefinitionRequest $request, string $capabilityId): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.capabilities.activate'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $capability = app(ActivateCapabilityDefinitionService::class)->execute(new ActivateCapabilityDefinitionCommand(
            $capabilityId,
            $validated['expected_version'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new CapabilityDefinitionResource($capability));
    }

    public function deprecate(DeprecateCapabilityDefinitionRequest $request, string $capabilityId): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.capabilities.deprecate'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $capability = app(DeprecateCapabilityDefinitionService::class)->execute(new DeprecateCapabilityDefinitionCommand(
            $capabilityId,
            $validated['expected_version'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new CapabilityDefinitionResource($capability));
    }

    public function retire(RetireCapabilityDefinitionRequest $request, string $capabilityId): JsonResponse
    {
        if (! ($decision = $this->authorize('commercial_catalogue.capabilities.retire'))) {
            return $this->deny($request);
        }

        $validated = $request->validated();
        $capability = app(RetireCapabilityDefinitionService::class)->execute(new RetireCapabilityDefinitionCommand(
            $capabilityId,
            $validated['expected_version'],
            $validated['occurred_at'],
            $this->actorId($decision),
            $request->correlationId(),
        ));

        return $this->resource($request, new CapabilityDefinitionResource($capability));
    }
}
