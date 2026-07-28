<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivatePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueVersionMismatchException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\RetirePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdatePlanDetailsService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\RetirePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdatePlanDetailsCommand;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Support\Authorization\Application\AuthorizationContext;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final readonly class SuperAdminCommercialPlanOperationController
{
    public function store(Request $request): RedirectResponse
    {
        try {
            $input = $this->planInput($request);
            $plan = app(CreatePlanService::class)->execute(new CreatePlanCommand(
                $input['code'],
                $input['name'],
                $input['description'],
                $input['display_order'],
                $this->now(),
                $this->actorId($request),
                (string) Str::uuid(),
            ));
        } catch (ValidationException $exception) {
            return $this->validationFailure($request, $exception);
        } catch (CommercialCatalogueVersionMismatchException|InvalidCommercialCatalogueValueException $exception) {
            return $this->failure($exception);
        }

        return redirect()
            ->route('dashboard.commercial.plans.show', $plan->id->value)
            ->with('operation', 'plan_created')
            ->setStatusCode(303);
    }

    public function update(
        Request $request,
        string $planId,
    ): RedirectResponse {
        try {
            $input = $this->planInput($request, true);
            app(UpdatePlanDetailsService::class)->execute(new UpdatePlanDetailsCommand(
                $planId,
                $input['name'],
                $input['description'],
                $input['display_order'],
                $input['expected_version'],
                $this->now(),
                $this->actorId($request),
                (string) Str::uuid(),
            ));
        } catch (ValidationException $exception) {
            return $this->validationFailure($request, $exception);
        } catch (CommercialCatalogueResourceNotFoundException|CommercialCatalogueVersionMismatchException|InvalidCommercialCatalogueValueException $exception) {
            return $this->failure($exception);
        }

        return redirect()
            ->route('dashboard.commercial.plans.show', $planId)
            ->with('operation', 'plan_updated')
            ->setStatusCode(303);
    }

    public function publish(
        Request $request,
        string $planId,
    ): RedirectResponse {
        try {
            app(ActivatePlanService::class)->execute(new ActivatePlanCommand(
                $planId,
                $this->expectedVersion($request),
                $this->now(),
                $this->actorId($request),
                (string) Str::uuid(),
            ));
        } catch (ValidationException $exception) {
            return $this->validationFailure($request, $exception);
        } catch (CommercialCatalogueResourceNotFoundException|CommercialCatalogueVersionMismatchException|InvalidCommercialCatalogueValueException $exception) {
            return $this->failure($exception);
        }

        return redirect()
            ->route('dashboard.commercial.plans.show', $planId)
            ->with('operation', 'plan_published')
            ->setStatusCode(303);
    }

    public function retire(
        Request $request,
        string $planId,
    ): RedirectResponse {
        try {
            app(RetirePlanService::class)->execute(new RetirePlanCommand(
                $planId,
                $this->expectedVersion($request),
                $this->now(),
                $this->actorId($request),
                (string) Str::uuid(),
            ));
        } catch (ValidationException $exception) {
            return $this->validationFailure($request, $exception);
        } catch (CommercialCatalogueResourceNotFoundException|CommercialCatalogueVersionMismatchException|InvalidCommercialCatalogueValueException $exception) {
            return $this->failure($exception);
        }

        return redirect()
            ->route('dashboard.commercial.plans.show', $planId)
            ->with('operation', 'plan_retired')
            ->setStatusCode(303);
    }

    /**
     * @return array{code: string, name: string, description: string, display_order: int, expected_version: int}
     */
    private function planInput(Request $request, bool $updating = false): array
    {
        $rules = [
            'code' => [$updating ? 'sometimes' : 'required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1000'],
            'display_order' => ['required', 'integer', 'min:0'],
        ];
        if ($updating) {
            $rules['expected_version'] = ['required', 'integer', 'min:1'];
        }

        /** @var array{code?: string, name: string, description: string, display_order: int, expected_version?: int} $validated */
        $validated = $request->validateWithBag('commercial', $rules);

        return [
            'code' => $validated['code'] ?? '',
            'name' => $validated['name'],
            'description' => $validated['description'],
            'display_order' => (int) $validated['display_order'],
            'expected_version' => (int) ($validated['expected_version'] ?? 0),
        ];
    }

    private function expectedVersion(Request $request): int
    {
        /** @var array{expected_version: int} $validated */
        $validated = $request->validateWithBag(
            'commercial',
            ['expected_version' => ['required', 'integer', 'min:1']],
        );

        return (int) $validated['expected_version'];
    }

    private function actorId(Request $request): string
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Super Admin authorization context was not established.');
        }

        return $context->identityId;
    }

    private function failure(Throwable $exception): RedirectResponse
    {
        return back()
            ->withInput()
            ->with('commercial_error', $exception->getMessage())
            ->setStatusCode(303);
    }

    private function validationFailure(
        Request $request,
        ValidationException $exception,
    ): RedirectResponse {
        return back()
            ->withErrors($exception->errors(), 'commercial')
            ->withInput($request->input())
            ->setStatusCode(303);
    }

    private function now(): string
    {
        return (new DateTimeImmutable)->format('Y-m-d\TH:i:s\Z');
    }
}
