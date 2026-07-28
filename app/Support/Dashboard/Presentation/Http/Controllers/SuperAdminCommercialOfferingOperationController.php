<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueVersionMismatchException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\RetirePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdatePlanOfferingService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\RetirePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidPlanOfferingException;
use App\Support\Authorization\Application\AuthorizationContext;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final readonly class SuperAdminCommercialOfferingOperationController
{
    public function store(Request $request): RedirectResponse
    {
        try {
            $input = $this->offeringInput($request);
            $offering = app(CreatePlanOfferingService::class)->execute(new CreatePlanOfferingCommand(
                $input['plan_id'],
                $input['billing_option_id'],
                $input['amount_minor'],
                'MYR',
                $input['effective_start'],
                $input['effective_end'],
                $input['capability_configuration_reference'],
                $input['display_order'],
                $this->now(),
                $this->actorId($request),
                (string) Str::uuid(),
            ));
        } catch (ValidationException $exception) {
            return $this->validationFailure($request, $exception);
        } catch (CommercialCatalogueResourceNotFoundException|CommercialCatalogueVersionMismatchException|InvalidCommercialCatalogueValueException|InvalidPlanOfferingException $exception) {
            return $this->failure($exception);
        }

        return redirect()
            ->route('dashboard.commercial.plans.offerings.show', [
                'planId' => $offering->planId->value,
                'offeringId' => $offering->id->value,
            ])
            ->with('operation', 'offering_created')
            ->setStatusCode(303);
    }

    public function update(
        Request $request,
        string $offeringId,
    ): RedirectResponse {
        try {
            $input = $this->offeringInput($request, true);
            app(UpdatePlanOfferingService::class)->execute(new UpdatePlanOfferingCommand(
                $offeringId,
                $input['amount_minor'],
                'MYR',
                $input['effective_start'],
                $input['effective_end'],
                $input['capability_configuration_reference'],
                $input['display_order'],
                $input['expected_version'],
                $this->now(),
                $this->actorId($request),
                (string) Str::uuid(),
            ));
        } catch (ValidationException $exception) {
            return $this->validationFailure($request, $exception);
        } catch (CommercialCatalogueResourceNotFoundException|CommercialCatalogueVersionMismatchException|InvalidCommercialCatalogueValueException|InvalidPlanOfferingException $exception) {
            return $this->failure($exception);
        }

        return redirect()
            ->route('dashboard.commercial.plans.offerings.show', [
                'planId' => $input['plan_id'],
                'offeringId' => $offeringId,
            ])
            ->with('operation', 'offering_updated')
            ->setStatusCode(303);
    }

    public function activate(
        Request $request,
        string $offeringId,
    ): RedirectResponse {
        try {
            app(ActivatePlanOfferingService::class)->execute(new ActivatePlanOfferingCommand(
                $offeringId,
                $this->expectedVersion($request),
                $this->now(),
                $this->actorId($request),
                (string) Str::uuid(),
            ));
        } catch (ValidationException $exception) {
            return $this->validationFailure($request, $exception);
        } catch (CommercialCatalogueResourceNotFoundException|CommercialCatalogueVersionMismatchException|InvalidCommercialCatalogueValueException|InvalidPlanOfferingException $exception) {
            return $this->failure($exception);
        }

        return back()->with('operation', 'offering_activated')->setStatusCode(303);
    }

    public function retire(
        Request $request,
        string $offeringId,
    ): RedirectResponse {
        try {
            app(RetirePlanOfferingService::class)->execute(new RetirePlanOfferingCommand(
                $offeringId,
                $this->expectedVersion($request),
                $this->now(),
                $this->actorId($request),
                (string) Str::uuid(),
            ));
        } catch (ValidationException $exception) {
            return $this->validationFailure($request, $exception);
        } catch (CommercialCatalogueResourceNotFoundException|CommercialCatalogueVersionMismatchException|InvalidCommercialCatalogueValueException|InvalidPlanOfferingException $exception) {
            return $this->failure($exception);
        }

        return back()->with('operation', 'offering_retired')->setStatusCode(303);
    }

    /**
     * @return array{
     *   plan_id: string,
     *   billing_option_id: string,
     *   amount_minor: int,
     *   effective_start: string,
     *   effective_end: ?string,
     *   capability_configuration_reference: string,
     *   display_order: int,
     *   expected_version: int
     * }
     */
    private function offeringInput(Request $request, bool $updating = false): array
    {
        $rules = [
            'plan_id' => ['required', 'uuid'],
            'billing_option_id' => ['required', 'uuid'],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'effective_start' => ['required', 'date_format:Y-m-d'],
            'effective_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_start'],
            'capability_configuration_reference' => ['required', 'string', 'max:100'],
            'display_order' => ['required', 'integer', 'min:0'],
        ];
        if ($updating) {
            $rules['expected_version'] = ['required', 'integer', 'min:1'];
        }

        /** @var array{
         *   plan_id: string,
         *   billing_option_id: string,
         *   amount_minor: int,
         *   effective_start: string,
         *   effective_end: ?string,
         *   capability_configuration_reference: string,
         *   display_order: int,
         *   expected_version: int
         * } $validated
         */
        $validated = $request->validateWithBag('commercial', $rules);
        if (! $updating) {
            $validated['expected_version'] = 0;
        }

        return [
            ...$validated,
            'amount_minor' => (int) $validated['amount_minor'],
            'display_order' => (int) $validated['display_order'],
            'expected_version' => (int) $validated['expected_version'],
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
