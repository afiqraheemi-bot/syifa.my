<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\DeprecateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueVersionMismatchException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\RetireCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\DeprecateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\RetireCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Support\Authorization\Application\AuthorizationContext;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final readonly class SuperAdminCommercialCapabilityOperationController
{
    public function store(Request $request): RedirectResponse
    {
        try {
            $input = $this->input($request);
            app(CreateCapabilityDefinitionService::class)->execute(new CreateCapabilityDefinitionCommand(
                $input['capability_key'],
                $input['name'],
                $input['description'],
                $input['commercial_meaning'],
                $this->now(),
                $this->actorId($request),
                (string) Str::uuid(),
            ));
        } catch (ValidationException $exception) {
            return $this->validationFailure($request, $exception);
        } catch (InvalidCommercialCatalogueValueException $exception) {
            return $this->failure($exception);
        }

        return redirect()
            ->route('dashboard.commercial')
            ->with('operation', 'capability_created')
            ->setStatusCode(303);
    }

    public function update(Request $request, string $capabilityId): RedirectResponse
    {
        try {
            $input = $this->input($request, true);
            app(UpdateCapabilityDefinitionService::class)->execute(new UpdateCapabilityDefinitionCommand(
                $capabilityId,
                $input['name'],
                $input['description'],
                $input['commercial_meaning'],
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
            ->route('dashboard.commercial')
            ->with('operation', 'capability_updated')
            ->setStatusCode(303);
    }

    public function activate(Request $request, string $capabilityId): RedirectResponse
    {
        return $this->transition(
            $request,
            $capabilityId,
            'capability_activated',
            fn (int $version, string $actor, string $correlation): mixed => app(ActivateCapabilityDefinitionService::class)
                ->execute(new ActivateCapabilityDefinitionCommand(
                    $capabilityId,
                    $version,
                    $this->now(),
                    $actor,
                    $correlation,
                )),
        );
    }

    public function deprecate(Request $request, string $capabilityId): RedirectResponse
    {
        return $this->transition(
            $request,
            $capabilityId,
            'capability_deprecated',
            fn (int $version, string $actor, string $correlation): mixed => app(DeprecateCapabilityDefinitionService::class)
                ->execute(new DeprecateCapabilityDefinitionCommand(
                    $capabilityId,
                    $version,
                    $this->now(),
                    $actor,
                    $correlation,
                )),
        );
    }

    public function retire(Request $request, string $capabilityId): RedirectResponse
    {
        return $this->transition(
            $request,
            $capabilityId,
            'capability_retired',
            fn (int $version, string $actor, string $correlation): mixed => app(RetireCapabilityDefinitionService::class)
                ->execute(new RetireCapabilityDefinitionCommand(
                    $capabilityId,
                    $version,
                    $this->now(),
                    $actor,
                    $correlation,
                )),
        );
    }

    /**
     * @param  callable(int, string, string): mixed  $operation
     */
    private function transition(
        Request $request,
        string $capabilityId,
        string $feedback,
        callable $operation,
    ): RedirectResponse {
        try {
            $operation(
                $this->expectedVersion($request),
                $this->actorId($request),
                (string) Str::uuid(),
            );
        } catch (ValidationException $exception) {
            return $this->validationFailure($request, $exception);
        } catch (CommercialCatalogueResourceNotFoundException|CommercialCatalogueVersionMismatchException|InvalidCommercialCatalogueValueException $exception) {
            return $this->failure($exception);
        }

        return redirect()
            ->route('dashboard.commercial')
            ->with('operation', $feedback)
            ->setStatusCode(303);
    }

    /**
     * @return array{
     *   capability_key: string,
     *   name: string,
     *   description: string,
     *   commercial_meaning: string,
     *   expected_version: int
     * }
     */
    private function input(Request $request, bool $updating = false): array
    {
        $rules = [
            'capability_key' => [$updating ? 'sometimes' : 'required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1000'],
            'commercial_meaning' => ['required', 'string', 'max:1000'],
        ];
        if ($updating) {
            $rules['expected_version'] = ['required', 'integer', 'min:1'];
        }

        /** @var array{capability_key?: string, name: string, description: string, commercial_meaning: string, expected_version?: int} $validated */
        $validated = $request->validateWithBag('commercial', $rules);

        return [
            'capability_key' => $validated['capability_key'] ?? '',
            'name' => $validated['name'],
            'description' => $validated['description'],
            'commercial_meaning' => $validated['commercial_meaning'],
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

    private function validationFailure(Request $request, ValidationException $exception): RedirectResponse
    {
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
