<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\ClinicBooking\ManageClinicBookingScheduleService;
use App\Modules\WebsiteBuilder\Application\ClinicBooking\ManageClinicBusinessHoursService;
use App\Modules\WebsiteBuilder\Application\ClinicBooking\UpdateClinicBusinessHoursCommand;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicOperationalTimeException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleClinicWriteException;
use App\Support\Authorization\Application\AuthorizationContext;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;

final readonly class ClinicOwnerBusinessHoursController
{
    public function __invoke(
        Request $request,
        ManageClinicBookingScheduleService $schedule,
        ManageClinicBusinessHoursService $businessHours,
    ): RedirectResponse {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext || $context->tenantId === null) {
            throw new LogicException('Clinic Owner Business Hours context was not established.');
        }

        /** @var array{version: int, timezone: string, operating_intervals: list<array{day: int, opens_at: string, closes_at: string}>} $data */
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'timezone' => ['required', 'in:Asia/Kuala_Lumpur'],
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
            $businessHours->update(new UpdateClinicBusinessHoursCommand(
                $context->tenantId,
                $clinic->clinicId,
                $authorization,
                $data['version'],
                $data['timezone'],
                $data['operating_intervals'],
                new DateTimeImmutable,
            ));
        } catch (StaleClinicWriteException) {
            return back()->withErrors([
                'business_hours.version' => 'Business Hours changed while you were editing. Refresh and try again.',
            ]);
        } catch (InvalidClinicOperationalTimeException $exception) {
            return back()->withErrors([
                'business_hours.configuration' => $exception->getMessage(),
            ]);
        }

        return back()->with('business_hours_saved', true);
    }
}
