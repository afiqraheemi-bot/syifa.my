<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreateBillingOptionService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateBillingOptionCommand;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Support\Authorization\Application\AuthorizationContext;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class SuperAdminCommercialBillingOptionOperationController
{
    public function store(Request $request, CreateBillingOptionService $service): RedirectResponse
    {
        try {
            $input = $this->input($request);
            $service->execute(new CreateBillingOptionCommand(
                $input['code'],
                $input['name'],
                $input['recurrence_classification'],
                $input['interval_unit'],
                $input['interval_count'],
                $input['display_order'],
                $input['effective_start'],
                $input['effective_end'],
                (new DateTimeImmutable)->format('Y-m-d\TH:i:s\Z'),
                $this->actorId($request),
                (string) Str::uuid(),
            ));
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors(), 'commercial')
                ->withInput($request->input())
                ->setStatusCode(303);
        } catch (InvalidCommercialCatalogueValueException $exception) {
            return back()
                ->withInput()
                ->with('commercial_error', $exception->getMessage())
                ->setStatusCode(303);
        }

        return redirect()
            ->route('dashboard.commercial')
            ->with('operation', 'billing_option_created')
            ->setStatusCode(303);
    }

    /**
     * @return array{
     *   code: string,
     *   name: string,
     *   recurrence_classification: string,
     *   interval_unit: ?string,
     *   interval_count: ?int,
     *   effective_start: string,
     *   effective_end: ?string,
     *   display_order: int
     * }
     */
    private function input(Request $request): array
    {
        /** @var array{
         *   code: string,
         *   name: string,
         *   recurrence_classification: string,
         *   interval_unit?: string|null,
         *   interval_count?: int|null,
         *   effective_start: string,
         *   effective_end?: string|null,
         *   display_order: int
         * } $validated
         */
        $validated = $request->validateWithBag('commercial', [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'recurrence_classification' => ['required', 'in:recurring,non_recurring'],
            'interval_unit' => ['required_if:recurrence_classification,recurring', 'nullable', 'in:month,year', 'prohibited_if:recurrence_classification,non_recurring'],
            'interval_count' => ['required_if:recurrence_classification,recurring', 'nullable', 'integer', 'min:1', 'prohibited_if:recurrence_classification,non_recurring'],
            'effective_start' => ['required', 'date_format:Y-m-d'],
            'effective_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_start'],
            'display_order' => ['required', 'integer', 'min:0'],
        ]);

        return [
            ...$validated,
            'interval_unit' => $validated['interval_unit'] ?? null,
            'interval_count' => isset($validated['interval_count']) ? (int) $validated['interval_count'] : null,
            'effective_end' => $validated['effective_end'] ?? null,
            'display_order' => (int) $validated['display_order'],
        ];
    }

    private function actorId(Request $request): string
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Super Admin authorization context was not established.');
        }

        return $context->identityId;
    }
}
