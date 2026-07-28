<?php

declare(strict_types=1);

namespace App\Modules\Booking\Presentation\Http\Controllers;

use App\Modules\Booking\Application\Commands\CreateManualBookingCommand;
use App\Modules\Booking\Application\CreateManualBookingService;
use App\Modules\Booking\Application\Exceptions\BookingFormConfigurationNotFoundException;
use App\Modules\Booking\Application\Exceptions\BookingOperationForbiddenException;
use App\Modules\Booking\Application\Exceptions\BookingServiceInactiveException;
use App\Modules\Booking\Application\Exceptions\BookingServiceNotFoundException;
use App\Modules\Booking\Application\Exceptions\DisabledBookingFieldSuppliedException;
use App\Modules\Booking\Application\Exceptions\InvalidClinicBookingConfigurationException;
use App\Modules\Booking\Application\Exceptions\RequiredBookingFieldMissingException;
use App\Modules\Booking\Application\Exceptions\SlotUnavailableException;
use App\Modules\Booking\Application\Exceptions\UnsupportedManualBookingSourceException;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeNotFoundException;
use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\Booking\Presentation\Http\Requests\CreateClinicOwnerManualBookingRequest;
use App\Support\Authorization\Application\AuthorizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ClinicOwnerManualBookingController
{
    public function __invoke(
        CreateClinicOwnerManualBookingRequest $request,
        CreateManualBookingService $bookings,
    ): RedirectResponse {
        $context = $request->attributes->get(AuthorizationContext::class);
        abort_unless($context instanceof AuthorizationContext && $context->tenantId !== null, 403);

        $tenantId = new TenantId($context->tenantId);

        try {
            $result = $bookings->execute(new CreateManualBookingCommand(
                tenantId: $tenantId,
                actorTenantId: $tenantId,
                actorId: $context->identityId,
                actorRole: $context->role,
                source: strtoupper($request->string('source')->toString()),
                patientName: $request->string('patient_name')->toString(),
                phone: $request->string('phone')->toString(),
                appointmentDate: $request->string('appointment_date')->toString(),
                appointmentTime: $request->string('appointment_time')->toString(),
                serviceId: $this->optionalString($request->input('service_id')),
                email: $this->optionalString($request->input('email')),
                notes: $this->optionalString($request->input('notes')),
            ));
        } catch (BookingOperationForbiddenException) {
            abort(403);
        } catch (BookingServiceNotFoundException|BookingServiceInactiveException $exception) {
            return back()->withInput()->withErrors(['service_id' => $exception->getMessage()]);
        } catch (SlotUnavailableException $exception) {
            return back()->withInput()->withErrors(['appointment_time' => $exception->getMessage()]);
        } catch (InvalidClinicBookingConfigurationException|ClinicOperationalTimeNotFoundException $exception) {
            return back()->withInput()->withErrors(['appointment_date' => $exception->getMessage()]);
        } catch (UnsupportedManualBookingSourceException $exception) {
            return back()->withInput()->withErrors(['source' => $exception->getMessage()]);
        } catch (BookingFormConfigurationNotFoundException|RequiredBookingFieldMissingException|DisabledBookingFieldSuppliedException|InvalidBookingValueException $exception) {
            return back()->withInput()->withErrors(['manual_booking' => $exception->getMessage()]);
        } catch (Throwable) {
            Log::error('Unexpected Clinic Owner manual Booking creation failure.', [
                'correlation_id' => $request->attributes->get('correlation_id'),
            ]);

            return back()->withInput()->withErrors([
                'manual_booking' => 'The booking could not be created. Please try again.',
            ]);
        }

        return to_route('dashboard.bookings', status: 303)
            ->with('manual_booking_success', "Booking {$result->reference} was created.");
    }

    private function optionalString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
