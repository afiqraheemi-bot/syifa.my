<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\ClinicBooking\ManageClinicBookingDateOverridesService;
use App\Modules\WebsiteBuilder\Application\ClinicBooking\ManageClinicBookingScheduleService;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicOperationalTimeException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleClinicWriteException;
use App\Support\Authorization\Application\AuthorizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;

final readonly class ClinicOwnerBookingDateOverrideController
{
    public function store(Request $request, ManageClinicBookingScheduleService $schedule, ManageClinicBookingDateOverridesService $overrides): RedirectResponse
    {
        $context = $this->context($request);
        $tenantId = $this->tenantId($context);
        /** @var array{local_date: string, closed: bool, version: int, intervals?: list<array{opens_at: string, closes_at: string}>} $data */
        $data = $request->validate([
            'local_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'closed' => ['required', 'boolean'],
            'version' => ['required', 'integer', 'min:0'],
            'intervals' => ['exclude_if:closed,true', 'required_if:closed,false', 'array', 'max:5'],
            'intervals.*.opens_at' => ['required', 'date_format:H:i'],
            'intervals.*.closes_at' => ['required', 'date_format:H:i'],
        ]);
        $authorization = $this->authorization($context);
        $clinic = $schedule->read($tenantId, $authorization);

        try {
            $overrides->save($tenantId, $clinic->clinicId, $authorization, $data['local_date'], $data['closed'], $data['intervals'] ?? [], $data['version']);
        } catch (StaleClinicWriteException) {
            return back()->withErrors(['date_override.version' => 'This date override changed while you were editing. Refresh and try again.']);
        } catch (InvalidClinicOperationalTimeException $exception) {
            return back()->withErrors(['date_override.configuration' => $exception->getMessage()]);
        }

        return back()->with('booking_date_override_saved', true);
    }

    public function destroy(Request $request, ManageClinicBookingScheduleService $schedule, ManageClinicBookingDateOverridesService $overrides, string $localDate): RedirectResponse
    {
        $context = $this->context($request);
        $tenantId = $this->tenantId($context);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $authorization = $this->authorization($context);
        $clinic = $schedule->read($tenantId, $authorization);
        try {
            $overrides->delete($tenantId, $clinic->clinicId, $authorization, $localDate, (int) $data['version']);
        } catch (StaleClinicWriteException) {
            return back()->withErrors(['date_override.version' => 'This date override changed while you were editing. Refresh and try again.']);
        }

        return back()->with('booking_date_override_deleted', true);
    }

    private function context(Request $request): AuthorizationContext
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext || $context->tenantId === null) {
            throw new LogicException('Clinic Owner Booking override context was not established.');
        }

        return $context;
    }

    private function authorization(AuthorizationContext $context): WebsiteAuthorizationContext
    {
        return new WebsiteAuthorizationContext($context->identityId, $context->role, actorTenantId: $context->tenantId);
    }

    private function tenantId(AuthorizationContext $context): string
    {
        if ($context->tenantId === null) {
            throw new LogicException('Clinic Owner Booking override Tenant context was not established.');
        }

        return $context->tenantId;
    }
}
