<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\ClinicBooking\ManageClinicBookingScheduleService;
use App\Modules\WebsiteBuilder\Application\Delivery\BookingDraft;
use App\Modules\WebsiteBuilder\Application\Delivery\ProtectedBookingPreviewService;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilityState;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingAvailabilityException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingBusinessRuleException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingInfrastructureException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingValidationException;
use App\Modules\WebsiteBuilder\Presentation\Http\BookingSubmissionTokenStore;
use App\Support\Authorization\Application\AuthorizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

final readonly class ClinicOwnerBookingPreviewController
{
    public function __invoke(
        Request $request,
        ProtectedBookingPreviewService $booking,
        BookingSubmissionTokenStore $submissionTokens,
        ManageClinicBookingScheduleService $schedule,
    ): View|RedirectResponse {
        $context = $request->attributes->get(AuthorizationContext::class);
        abort_unless($context instanceof AuthorizationContext && $context->tenantId !== null, 403);

        $tenantId = $context->tenantId;
        $authorization = new WebsiteAuthorizationContext(
            $context->identityId,
            $context->role,
            actorTenantId: $tenantId,
        );

        if ($request->isMethod('post')) {
            return $this->submit($request, $booking, $submissionTokens, $tenantId);
        }

        $selectedDate = $request->query('appointment_date');
        $selectedDate = is_string($selectedDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) === 1
            ? $selectedDate
            : null;
        $selectedServiceId = $request->query('service_id', $request->old('service_id'));
        $selectedServiceId = is_string($selectedServiceId) && $selectedServiceId !== ''
            ? $selectedServiceId
            : null;
        $slots = $selectedDate === null ? [] : array_values(array_filter(
            $booking->availability($tenantId, $selectedDate),
            static fn ($slot): bool => $slot->state === PublicAvailabilityState::Available,
        ));

        return view('public-website.booking.preview', [
            'configuration' => $booking->configuration($tenantId),
            'theme' => $booking->theme($tenantId),
            'schedule' => $schedule->read($tenantId, $authorization),
            'selectedDate' => $selectedDate,
            'selectedServiceId' => $selectedServiceId,
            'slots' => $slots,
            'submissionToken' => $submissionTokens->issue(),
            'submitUrl' => route('dashboard.website.booking-preview'),
            'backUrl' => route('dashboard.website.preview'),
        ]);
    }

    private function submit(
        Request $request,
        ProtectedBookingPreviewService $booking,
        BookingSubmissionTokenStore $submissionTokens,
        string $tenantId,
    ): RedirectResponse {
        $redirect = route('dashboard.website.booking-preview', [
            'appointment_date' => (string) $request->input('appointment_date', ''),
            'service_id' => (string) $request->input('service_id', ''),
        ]);
        $configuration = $booking->configuration($tenantId);
        $validator = Validator::make($request->all(), [
            'service_id' => [$configuration->serviceSelectionRequired ? 'required' : 'nullable', 'string', 'max:255'],
            'appointment_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'patient_name' => ['required', 'string', 'max:200'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:254'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'consent' => ['accepted'],
            'submission_token' => ['required', 'string'],
        ], [
            'service_id.required' => 'Choose a service.',
            'appointment_date.required' => 'Choose an appointment date.',
            'appointment_time.required' => 'Choose an available appointment time.',
            'patient_name.required' => 'Enter the patient name.',
            'phone.required' => 'Enter a phone number.',
            'email.email' => 'Enter a valid email address.',
            'consent.accepted' => 'Confirm that the clinic may contact the patient about this booking.',
        ]);
        if ($validator->fails()) {
            return redirect($redirect)->withInput()->withErrors($validator);
        }
        $validated = $validator->validated();

        if (! $submissionTokens->consume($validated['submission_token'])) {
            return redirect($redirect)->withInput()->withErrors([
                'submission' => 'This booking form has expired. Review the details and submit again.',
            ]);
        }

        $serviceId = trim((string) ($validated['service_id'] ?? ''));
        $draft = (new BookingDraft)
            ->withService($serviceId === '' ? null : $serviceId)
            ->withDate($validated['appointment_date'])
            ->withTime($validated['appointment_time'])
            ->withPatientDetails(
                $validated['patient_name'],
                $validated['phone'],
                $validated['email'] ?? null,
                $validated['notes'] ?? null,
                true,
            );

        try {
            $success = $booking->submit($tenantId, $draft);
        } catch (PublicBookingValidationException $exception) {
            return redirect($redirect)->withInput()->withErrors(['booking' => $exception->getMessage()]);
        } catch (PublicBookingAvailabilityException $exception) {
            return redirect($redirect)->withInput($request->except('appointment_time'))->withErrors(['appointment_time' => $exception->getMessage()]);
        } catch (PublicBookingBusinessRuleException $exception) {
            return redirect($redirect)->withInput()->withErrors(['booking' => $exception->getMessage()]);
        } catch (PublicBookingInfrastructureException) {
            return redirect($redirect)->withInput()->withErrors(['booking' => 'Booking could not be completed. Please try again.']);
        } catch (Throwable $exception) {
            Log::error('Unexpected Clinic Owner booking preview submission failure.', [
                'correlation_id' => $request->attributes->get('correlation_id'),
                'exception' => $exception::class,
            ]);

            return redirect($redirect)->withInput()->withErrors(['booking' => 'Booking could not be completed. Please try again.']);
        }

        return redirect()->route('dashboard.website.booking-preview')->with(
            'booking_preview_success',
            sprintf('Booking %s has been received.', $success->reference),
        );
    }
}
