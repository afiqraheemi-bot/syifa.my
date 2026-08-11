<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\SuperAdmin\Commercial\CreateSubscriptionPackageApplication;
use App\Support\Dashboard\Application\SuperAdmin\Commercial\CreateSubscriptionPackageCommand;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SuperAdminCommercialPackageOperationController
{
    public function store(
        Request $request,
        CreateSubscriptionPackageApplication $application,
    ): RedirectResponse {
        try {
            $request->merge([
                'code' => strtolower(trim((string) $request->input('code', ''))),
            ]);

            if ($request->exists('price_myr')) {
                $request->merge([
                    'amount_minor' => $this->minorUnits((string) $request->input('price_myr')),
                ]);
            }

            /** @var array{code: string, name: string, description: string, billing_option_id: string, amount_minor: int, effective_start: string, effective_end?: string|null} $input */
            $input = $request->validateWithBag('commercial', [
                'code' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_-]{0,49}$/'],
                'name' => ['required', 'string', 'max:100'],
                'description' => ['required', 'string', 'max:1000'],
                'billing_option_id' => ['required', 'uuid'],
                'amount_minor' => ['required', 'integer', 'min:1'],
                'effective_start' => ['required', 'date_format:Y-m-d'],
                'effective_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_start'],
            ], [
                'code.regex' => 'Use letters, numbers, hyphens or underscores, starting with a letter.',
            ]);

            $context = $request->attributes->get(AuthorizationContext::class);
            if (! $context instanceof AuthorizationContext) {
                abort(403);
            }

            $result = $application->execute(new CreateSubscriptionPackageCommand(
                $input['code'],
                $input['name'],
                $input['description'],
                $input['billing_option_id'],
                $input['amount_minor'],
                $input['effective_start'],
                ($input['effective_end'] ?? '') === '' ? null : $input['effective_end'],
                (new DateTimeImmutable('now'))->format('Y-m-d\TH:i:s\Z'),
                $context->identityId,
                (string) Str::uuid(),
            ));
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors(), 'commercial')
                ->withInput($request->input())
                ->setStatusCode(303);
        } catch (InvalidCommercialCatalogueValueException $exception) {
            return redirect()
                ->back(303)
                ->withInput()
                ->with('commercial_error', $exception->getMessage());
        }

        return redirect()
            ->route('dashboard.commercial.plans.show', $result->planId)
            ->with('operation', 'package_created')
            ->setStatusCode(303);
    }

    private function minorUnits(string $amount): int
    {
        $normalized = trim($amount);
        if (preg_match('/^(?<ringgit>\d{1,9})(?:\.(?<sen>\d{1,2}))?$/', $normalized, $parts) !== 1) {
            throw ValidationException::withMessages([
                'price_myr' => ['Enter a valid MYR price with no more than two decimal places.'],
            ]);
        }

        $sen = str_pad((string) ($parts['sen'] ?? ''), 2, '0');

        return ((int) $parts['ringgit'] * 100) + (int) $sen;
    }
}
