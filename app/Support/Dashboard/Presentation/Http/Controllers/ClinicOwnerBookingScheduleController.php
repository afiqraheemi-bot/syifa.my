<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\ClinicBooking\ManageClinicBookingScheduleService;
use App\Modules\WebsiteBuilder\Application\ClinicBooking\UpdateClinicBookingScheduleCommand;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicOperationalTimeException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleClinicWriteException;
use App\Support\Authorization\Application\AuthorizationContext;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use LogicException;

final readonly class ClinicOwnerBookingScheduleController
{
    public function __invoke(
        Request $request,
        ManageClinicBookingScheduleService $schedule,
    ): RedirectResponse {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext || $context->tenantId === null) {
            throw new LogicException('Clinic Owner Booking schedule context was not established.');
        }

        /** @var array{version: int, timezone: string, appointment_duration_minutes: int, booking_capacity_per_slot: int, operating_intervals: list<array{day: int, opens_at: string, closes_at: string}>} $data */
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'timezone' => ['required', 'in:Asia/Kuala_Lumpur'],
            'appointment_duration_minutes' => ['required', 'integer', Rule::in([15, 20, 30, 45, 60])],
            'booking_capacity_per_slot' => ['required', 'integer', 'between:1,10'],
            'operating_intervals' => ['required', 'array', 'min:1', 'max:35'],
            'operating_intervals.*.day' => ['required', 'integer', 'between:1,7'],
            'operating_intervals.*.opens_at' => ['required', 'date_format:H:i'],
            'operating_intervals.*.closes_at' => ['required', 'date_format:H:i'],
        ]);

        $authorization = new WebsiteAuthorizationContext(
            $context->identityId,
            $context->role,
            actorTenantId: $context->tenantId,
        );
        $clinic = $schedule->read($context->tenantId, $authorization);

        try {
            $schedule->update(new UpdateClinicBookingScheduleCommand(
                $context->tenantId,
                $clinic->clinicId,
                $authorization,
                $data['version'],
                $data['timezone'],
                $data['operating_intervals'],
                $data['appointment_duration_minutes'],
                $data['booking_capacity_per_slot'],
                new DateTimeImmutable,
            ));
        } catch (StaleClinicWriteException) {
            return back()->withErrors([
                'schedule.version' => 'The Booking schedule changed while you were editing. Refresh and try again.',
            ]);
        } catch (InvalidClinicOperationalTimeException $exception) {
            return back()->withErrors([
                'schedule.configuration' => $exception->getMessage(),
            ]);
        }

        return back()->with('booking_schedule_saved', true);
    }
}
