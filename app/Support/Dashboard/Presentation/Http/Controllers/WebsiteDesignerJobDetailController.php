<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\Booking\Application\Configuration\ManageBookingFormConfigurationService;
use App\Modules\Booking\Application\Configuration\UpdateBookingFormConfigurationCommand;
use App\Modules\Booking\Domain\Exceptions\InvalidBookingFormConfigurationValueException;
use App\Modules\Booking\Domain\Exceptions\StaleBookingFormConfigurationWriteException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidOnboardingJobLifecycleTransitionException;
use App\Modules\WebsiteBuilder\Application\ClinicBooking\ManageClinicBookingScheduleService;
use App\Modules\WebsiteBuilder\Application\ClinicBooking\UpdateClinicBookingScheduleCommand;
use App\Modules\WebsiteBuilder\Application\ClinicContact\OptionalContactValue;
use App\Modules\WebsiteBuilder\Application\ClinicContact\UpdateClinicContactProfileCommand;
use App\Modules\WebsiteBuilder\Application\ClinicContact\UpdateClinicContactProfileService;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\ManageWebsiteContentService;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\UpdateWebsiteContentCommand;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicContactProfileException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicOperationalTimeException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleClinicWriteException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\WebsiteDesigner\Job\SubmitWebsiteForReviewApplication;
use App\Support\Dashboard\Application\WebsiteDesigner\Job\WebsiteDesignerJobDetailPage;
use App\Support\Dashboard\Presentation\Http\Requests\UpdateClinicOwnerWebsiteContentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WebsiteDesignerJobDetailController
{
    public function __invoke(
        Request $request,
        WebsiteDesignerJobDetailPage $page,
        ManageWebsiteContentService $websiteContent,
        ManageBookingFormConfigurationService $bookingConfiguration,
        UpdateClinicContactProfileService $clinicContact,
        ManageClinicBookingScheduleService $clinicBookingSchedule,
        SubmitWebsiteForReviewApplication $readyForReview,
        string $jobId,
    ): Response|RedirectResponse|JsonResponse {
        $context = $this->context($request);
        $view = $page->fromTrustedContext(
            $context,
            $jobId,
        );
        abort_if($view === null, 404);

        if ($request->isMethod('patch')) {
            $request->validate([
                'workspace' => ['required', Rule::in([
                    'ready_for_review',
                    'booking_configuration',
                    'booking_schedule',
                    'clinic_contact',
                    'website_setup',
                ])],
            ]);

            return match ($request->input('workspace')) {
                'ready_for_review' => $this->readyForReview(
                    $request,
                    $context,
                    $view->props,
                    $readyForReview,
                ),
                'booking_configuration' => $this->updateBookingConfiguration(
                    $request,
                    $view->props,
                    $bookingConfiguration,
                ),
                'booking_schedule' => $this->updateBookingSchedule(
                    $request,
                    $context,
                    $view->props,
                    $clinicBookingSchedule,
                ),
                'clinic_contact' => $this->updateClinicContact(
                    $request,
                    $context,
                    $view->props,
                    $clinicContact,
                ),
                'website_setup' => $this->updateWebsiteContent(
                    $request,
                    $context,
                    $view->props,
                    $websiteContent,
                ),
                default => abort(422),
            };
        }

        return Inertia::render($view->component, $view->props);
    }

    /** @param array<string, mixed> $props */
    private function updateWebsiteContent(
        Request $request,
        AuthorizationContext $context,
        array $props,
        ManageWebsiteContentService $websiteContent,
    ): RedirectResponse {
        $rules = (new UpdateClinicOwnerWebsiteContentRequest)->rules();
        $rules['template_id'] = ['required', Rule::enum(TemplateId::class)];
        /** @var array<string, mixed> $data */
        $data = $request->validate($rules);
        $branding = $data['branding'];
        $seo = $data['seo'];
        $tenantId = (string) $props['job']['tenantId'];

        try {
            $websiteContent->update(new UpdateWebsiteContentCommand(
                new WebsiteAuthorizationContext(
                    $context->identityId,
                    $context->role,
                    assignedTenantId: $tenantId,
                ),
                $tenantId,
                (int) $data['version'],
                trim((string) $branding['clinic_name']),
                $this->optional($branding['tagline'] ?? null),
                strtoupper((string) $branding['primary_color']),
                strtoupper((string) $branding['secondary_color']),
                trim((string) $branding['contact_email']),
                trim((string) $branding['contact_phone']),
                trim((string) $branding['address']),
                array_filter(
                    $branding['social_links'],
                    static fn (mixed $value): bool => is_string($value) && trim($value) !== '',
                ),
                trim((string) $seo['meta_title']),
                trim((string) $seo['meta_description']),
                $this->optional($seo['meta_keywords'] ?? null),
                $this->optional($seo['canonical_url'] ?? null),
                (string) $seo['robots_directive'],
                trim((string) $seo['open_graph_title']),
                trim((string) $seo['open_graph_description']),
                (bool) $seo['indexing_enabled'],
                $data['sections'],
                (string) $data['template_id'],
                $this->optional($branding['logo_reference'] ?? null),
                (string) $branding['logo_display_size'],
            ));
        } catch (StaleWebsiteWriteException) {
            return back()->withErrors([
                'version' => 'This Website configuration changed while you were editing. Refresh and review the latest values before saving again.',
            ]);
        } catch (InvalidWebsiteValueException $exception) {
            return back()->withErrors([
                'template_id' => $exception->getMessage(),
            ]);
        }

        return back()->with('website_setup_saved', true);
    }

    private function context(Request $request): AuthorizationContext
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext) {
            throw new \LogicException('Website Designer authorization context was not established.');
        }

        return $context;
    }

    private function optional(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $props */
    private function updateBookingConfiguration(
        Request $request,
        array $props,
        ManageBookingFormConfigurationService $bookingConfiguration,
    ): RedirectResponse {
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'workspace' => ['required', 'in:booking_configuration'],
            'version' => ['required', 'integer', 'min:1'],
            'service_selection_enabled' => ['required', 'boolean'],
            'service_required' => ['required', 'boolean'],
            'email_enabled' => ['required', 'boolean'],
            'email_required' => ['required', 'boolean'],
            'notes_enabled' => ['required', 'boolean'],
            'notes_required' => ['required', 'boolean'],
            'field_order' => ['required', 'array', 'min:4', 'max:7'],
            'field_order.*' => [
                'required',
                'distinct',
                Rule::in([
                    'patient_name',
                    'phone',
                    'appointment_date',
                    'appointment_time',
                    'service',
                    'email',
                    'notes',
                ]),
            ],
            'labels' => [
                'required',
                'array:patient_name,phone,appointment_date,appointment_time,service,email,notes',
            ],
            'labels.*' => ['nullable', 'string', 'max:120'],
        ]);
        $tenantId = (string) $props['job']['tenantId'];

        try {
            $bookingConfiguration->update(new UpdateBookingFormConfigurationCommand(
                $tenantId,
                (int) $data['version'],
                (bool) $data['service_selection_enabled'],
                (bool) $data['service_required'],
                (bool) $data['email_enabled'],
                (bool) $data['email_required'],
                (bool) $data['notes_enabled'],
                (bool) $data['notes_required'],
                $data['field_order'],
                array_filter(
                    $data['labels'],
                    static fn (mixed $label): bool => is_string($label) && trim($label) !== '',
                ),
            ));
        } catch (StaleBookingFormConfigurationWriteException) {
            return back()->withErrors([
                'booking.version' => 'Booking configuration changed while you were editing. Refresh and try again.',
            ]);
        } catch (InvalidBookingFormConfigurationValueException $exception) {
            return back()->withErrors([
                'booking.configuration' => $exception->getMessage(),
            ]);
        }

        return back()->with('booking_configuration_saved', true);
    }

    /** @param array<string, mixed> $props */
    private function updateBookingSchedule(
        Request $request,
        AuthorizationContext $context,
        array $props,
        ManageClinicBookingScheduleService $schedule,
    ): RedirectResponse {
        /** @var array{workspace: string, version: int, timezone: string, appointment_duration_minutes: int, booking_capacity_per_slot: int, operating_intervals: list<array{day: int, opens_at: string, closes_at: string}>} $data */
        $data = $request->validate([
            'workspace' => ['required', 'in:booking_schedule'],
            'version' => ['required', 'integer', 'min:1'],
            'timezone' => ['required', 'in:Asia/Kuala_Lumpur'],
            'appointment_duration_minutes' => ['required', 'integer', Rule::in([15, 20, 30, 45, 60])],
            'booking_capacity_per_slot' => ['required', 'integer', 'between:1,10'],
            'operating_intervals' => ['required', 'array', 'min:1', 'max:7'],
            'operating_intervals.*.day' => ['required', 'integer', 'between:1,7', 'distinct'],
            'operating_intervals.*.opens_at' => ['required', 'date_format:H:i'],
            'operating_intervals.*.closes_at' => ['required', 'date_format:H:i'],
        ]);
        $tenantId = (string) $props['job']['tenantId'];
        $authorization = new WebsiteAuthorizationContext(
            $context->identityId,
            $context->role,
            assignedTenantId: $tenantId,
        );
        $clinic = $schedule->read($tenantId, $authorization);

        try {
            $schedule->update(new UpdateClinicBookingScheduleCommand(
                $tenantId,
                $clinic->clinicId,
                $authorization,
                (int) $data['version'],
                (string) $data['timezone'],
                $data['operating_intervals'],
                (int) $data['appointment_duration_minutes'],
                (int) $data['booking_capacity_per_slot'],
                new \DateTimeImmutable,
            ));
        } catch (StaleClinicWriteException) {
            return back()->withErrors([
                'booking_schedule.version' => 'Booking schedule changed while you were editing. Refresh and try again.',
            ]);
        } catch (InvalidClinicOperationalTimeException $exception) {
            return back()->withErrors([
                'booking_schedule.configuration' => $exception->getMessage(),
            ]);
        }

        return back()->with('booking_schedule_saved', true);
    }

    /** @param array<string, mixed> $props */
    private function updateClinicContact(
        Request $request,
        AuthorizationContext $context,
        array $props,
        UpdateClinicContactProfileService $clinicContact,
    ): RedirectResponse {
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'workspace' => ['required', 'in:clinic_contact'],
            'version' => ['required', 'integer', 'min:1'],
            'operational_phone' => ['nullable', 'string', 'max:40'],
            'operational_email' => ['nullable', 'email:rfc', 'max:254'],
            'postal_address' => ['nullable', 'string', 'max:500'],
            'whatsapp_number' => ['nullable', 'string', 'max:40'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ]);
        $tenantId = (string) $props['job']['tenantId'];
        $current = $clinicContact->read(
            $tenantId,
            new WebsiteAuthorizationContext(
                $context->identityId,
                $context->role,
                assignedTenantId: $tenantId,
            ),
        );

        try {
            $clinicContact->handle(new UpdateClinicContactProfileCommand(
                $tenantId,
                $current->clinicId,
                new WebsiteAuthorizationContext(
                    $context->identityId,
                    $context->role,
                    assignedTenantId: $tenantId,
                ),
                $this->contactValue($data['operational_phone'] ?? null),
                $this->contactValue($data['operational_email'] ?? null),
                $this->contactValue($data['postal_address'] ?? null),
                $this->contactValue($data['whatsapp_number'] ?? null),
                $this->coordinateValue($data['latitude'] ?? null),
                $this->coordinateValue($data['longitude'] ?? null),
                new \DateTimeImmutable,
                (string) Str::uuid(),
                (int) $data['version'],
            ));
        } catch (StaleClinicWriteException) {
            return back()->withErrors([
                'clinic_contact.version' => 'Clinic contact details changed while you were editing. Refresh and try again.',
            ]);
        } catch (InvalidClinicContactProfileException $exception) {
            return back()->withErrors([
                'clinic_contact.configuration' => $exception->getMessage(),
            ]);
        }

        return back()->with('clinic_contact_saved', true);
    }

    private function contactValue(mixed $value): OptionalContactValue
    {
        return is_string($value) && trim($value) !== ''
            ? OptionalContactValue::with(trim($value))
            : OptionalContactValue::clear();
    }

    private function coordinateValue(mixed $value): OptionalContactValue
    {
        return $value === null || $value === ''
            ? OptionalContactValue::clear()
            : OptionalContactValue::with((float) $value);
    }

    /** @param array<string, mixed> $props */
    private function readyForReview(
        Request $request,
        AuthorizationContext $context,
        array $props,
        SubmitWebsiteForReviewApplication $service,
    ): JsonResponse {
        /** @var array{workspace: string, version: int, draft_version: int, job_version: int} $data */
        $data = $request->validate([
            'workspace' => ['required', 'in:ready_for_review'],
            'version' => ['required', 'integer', 'min:1'],
            'draft_version' => ['required', 'integer', 'min:1'],
            'job_version' => ['required', 'integer', 'min:1'],
        ]);
        $tenantId = (string) $props['job']['tenantId'];
        $correlationId = (string) Str::uuid();

        try {
            $website = $service->execute(
                new WebsiteAuthorizationContext(
                    $context->identityId,
                    $context->role,
                    assignedTenantId: $tenantId,
                ),
                $tenantId,
                (string) $props['job']['websiteId'],
                (string) $props['job']['id'],
                (string) Str::uuid(),
                $data['version'],
                $data['draft_version'],
                $data['job_version'],
                $correlationId,
                new \DateTimeImmutable,
            );
        } catch (StaleWebsiteWriteException $exception) {
            return response()->json([
                'message' => 'The Website changed. Refresh before submitting it for review.',
                'detail' => $exception->getMessage(),
            ], 409);
        } catch (InvalidWebsiteValueException $exception) {
            return response()->json([
                'message' => 'The Website is not ready for review.',
                'detail' => $exception->getMessage(),
            ], 422);
        } catch (InvalidOnboardingJobLifecycleTransitionException $exception) {
            return response()->json([
                'message' => 'The Onboarding Job could not enter Website review.',
                'detail' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Website submitted for review.',
            'data' => $website->toArray(),
        ]);
    }
}
