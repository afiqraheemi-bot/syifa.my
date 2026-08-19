<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\Booking\Application\ServiceSetup\ManageServiceSetupService;
use App\Modules\Booking\Application\ServiceSetup\SaveServiceCommand;
use App\Modules\Booking\Application\ServiceSetup\ServiceSetupEntitlementDeniedException;
use App\Modules\Booking\Application\ServiceSetup\ServiceSetupNotFoundException;
use App\Modules\Booking\Domain\Exceptions\InvalidServiceValueException;
use App\Modules\Booking\Domain\Exceptions\StaleServiceWriteException;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\Booking\ClinicOwnerServiceSetupPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ClinicOwnerServiceSetupController
{
    public function index(Request $request, ClinicOwnerServiceSetupPage $page): Response
    {
        $view = $page->fromTrustedContext($request->attributes->get(AuthorizationContext::class));

        return Inertia::render($view->component, $view->props);
    }

    public function store(Request $request, ManageServiceSetupService $services): RedirectResponse
    {
        $data = $this->validateService($request, false);
        $context = $this->context($request);

        return $this->execute(fn () => $services->save(new SaveServiceCommand(
            $this->tenantId($context),
            $context->identityId,
            (string) Str::uuid(),
            null,
            (string) $data['name'],
            $this->optional($data['description'] ?? null),
            (int) $data['sort_order'],
            null,
        )), 'Service created.');
    }

    public function update(Request $request, string $serviceId, ManageServiceSetupService $services): RedirectResponse
    {
        $data = $this->validateService($request, true);
        $context = $this->context($request);

        return $this->execute(fn () => $services->save(new SaveServiceCommand(
            $this->tenantId($context),
            $context->identityId,
            (string) Str::uuid(),
            $serviceId,
            (string) $data['name'],
            $this->optional($data['description'] ?? null),
            (int) $data['sort_order'],
            (int) $data['version'],
        )), 'Service updated.');
    }

    public function status(Request $request, string $serviceId, ManageServiceSetupService $services): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'active' => ['required', 'boolean']]);
        $context = $this->context($request);

        return $this->execute(fn () => $services->setActive(
            $this->tenantId($context),
            $context->identityId,
            (string) Str::uuid(),
            $serviceId,
            (int) $data['version'],
            (bool) $data['active'],
        ), (bool) $data['active'] ? 'Service activated.' : 'Service deactivated.');
    }

    /** @return array<string, mixed> */
    private function validateService(Request $request, bool $updating): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'version' => [$updating ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);
    }

    private function context(Request $request): AuthorizationContext
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        abort_unless($context instanceof AuthorizationContext && $context->tenantId !== null, 403);

        return $context;
    }

    private function tenantId(AuthorizationContext $context): string
    {
        if ($context->tenantId === null) {
            throw new \LogicException('Clinic Owner Service Setup requires a trusted Tenant identifier.');
        }

        return $context->tenantId;
    }

    private function execute(callable $operation, string $message): RedirectResponse
    {
        try {
            $operation();
        } catch (ServiceSetupNotFoundException) {
            abort(404);
        } catch (ServiceSetupEntitlementDeniedException $exception) {
            return back()->withErrors(['service' => $exception->getMessage()]);
        } catch (StaleServiceWriteException) {
            return back()->withErrors(['service' => 'This service changed while you were editing. Reload and try again.']);
        } catch (InvalidServiceValueException $exception) {
            return back()->withErrors(['service' => $exception->getMessage()]);
        }

        return back()->with('success', $message);
    }

    private function optional(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
