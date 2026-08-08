<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Delivery\ViewModels\AvailabilityChipViewData;
use App\Modules\WebsiteBuilder\Application\Delivery\ViewModels\BookingLandingViewModel;
use App\Modules\WebsiteBuilder\Application\Delivery\ViewModels\BookingThemeViewModel;
use App\Modules\WebsiteBuilder\Application\Delivery\ViewModels\DateSelectionViewModel;
use App\Modules\WebsiteBuilder\Application\Delivery\ViewModels\PatientDetailsViewModel;
use App\Modules\WebsiteBuilder\Application\Delivery\ViewModels\ReviewViewModel;
use App\Modules\WebsiteBuilder\Application\Delivery\ViewModels\ServiceOptionViewData;
use App\Modules\WebsiteBuilder\Application\Delivery\ViewModels\ServiceSelectionViewModel;
use App\Modules\WebsiteBuilder\Application\Delivery\ViewModels\SuccessViewModel;
use App\Modules\WebsiteBuilder\Application\Delivery\ViewModels\TimeSelectionViewModel;
use App\Modules\WebsiteBuilder\Contracts\Delivery\BookingSubmissionGatewayInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilitySlot;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilityState;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingAvailabilityException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingBusinessRuleException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfiguration;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfigurationReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingInfrastructureException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingSubmission;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingValidationException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\WebsiteTenantResolverInterface;
use DateTimeImmutable;

/**
 * The sole orchestrator of Booking's journey (ADR-029): the only caller of
 * `AvailabilityDeliveryService` and `BookingSubmissionGatewayInterface`, and
 * the one place every ViewModel is built (ADR-029: "All five ViewModels are
 * built exclusively by the Booking Delivery Service — never by a Controller,
 * never inside Blade"). Framework-free by design (no framework reference is
 * permitted in `Application/Delivery`) — session I/O (reading/writing the
 * Draft, old-input replay) stays in the Controller, which is why ViewModel
 * methods here accept an already-loaded `BookingDraft` value rather than a
 * session store.
 */
final readonly class BookingDeliveryService
{
    public function __construct(
        private WebsiteTenantResolverInterface $tenants,
        private AvailabilityDeliveryService $availability,
        private BookingSubmissionGatewayInterface $submissions,
        private PublicBookingFormConfigurationReaderInterface $formConfigurations,
        private PublicWebsiteRenderModelProviderInterface $websites,
    ) {}

    public function formConfigurationFor(string $trustedWebsiteId): PublicBookingFormConfiguration
    {
        return $this->formConfigurations->forTrustedTenant($this->tenants->forTrustedWebsite($trustedWebsiteId));
    }

    /** @return list<PublicAvailabilitySlot> */
    public function availabilityForDate(string $trustedWebsiteId, string $localDate): array
    {
        return $this->availability->slotsForDate($trustedWebsiteId, $localDate);
    }

    public function landingViewModel(PublicSiteContext $context): BookingLandingViewModel
    {
        $formConfiguration = $this->formConfigurationFor((string) $context->websiteId);

        return new BookingLandingViewModel(1, $this->totalSteps($formConfiguration), $this->whatsAppUrl($context));
    }

    public function themeViewModel(PublicSiteContext $context): BookingThemeViewModel
    {
        $website = $this->websites->find($context);
        if ($website === null) {
            return (new BookingThemeViewModelFactory)->make(null);
        }

        return (new BookingThemeViewModelFactory)->make(
            $website->website->templateId,
            $website->branding->primaryColor,
            $website->branding->secondaryColor,
        );
    }

    /** Returns null when Service Selection is disabled — the Controller redirects straight to Date Selection. */
    public function serviceSelectionViewModel(PublicSiteContext $context, BookingDraft $draft): ?ServiceSelectionViewModel
    {
        $formConfiguration = $this->formConfigurationFor((string) $context->websiteId);
        if (! $formConfiguration->serviceSelectionEnabled) {
            return null;
        }

        $options = [];
        foreach ($formConfiguration->services as $service) {
            $options[] = new ServiceOptionViewData($service->id, $service->name, $service->featured, $draft->serviceId === $service->id);
        }
        if (! $formConfiguration->serviceSelectionRequired) {
            $options[] = new ServiceOptionViewData('', 'Not sure / General appointment', false, $draft->serviceId === null);
        }

        return new ServiceSelectionViewModel(1, $this->totalSteps($formConfiguration), $options, $formConfiguration->serviceSelectionRequired);
    }

    public function dateSelectionViewModel(PublicSiteContext $context, BookingDraft $draft): DateSelectionViewModel
    {
        $formConfiguration = $this->formConfigurationFor((string) $context->websiteId);
        $today = new DateTimeImmutable('today');
        $dates = [];
        for ($offset = 0; $offset < 14; $offset++) {
            $date = $today->modify(sprintf('+%d days', $offset))->format('Y-m-d');
            $slots = $this->availability->slotsForDate((string) $context->websiteId, $date);
            $dates[] = new AvailabilityChipViewData($date, $this->dateLabel($date, $offset), $this->aggregateState($slots), $draft->appointmentDate === $date);
        }

        return new DateSelectionViewModel($this->dateStep($formConfiguration), $this->totalSteps($formConfiguration), $dates, $draft->appointmentDate);
    }

    public function timeSelectionViewModel(PublicSiteContext $context, BookingDraft $draft): ?TimeSelectionViewModel
    {
        if ($draft->appointmentDate === null) {
            return null;
        }
        $formConfiguration = $this->formConfigurationFor((string) $context->websiteId);
        $slots = $this->availability->slotsForDate((string) $context->websiteId, $draft->appointmentDate);

        $times = array_map(
            fn (PublicAvailabilitySlot $slot): AvailabilityChipViewData => new AvailabilityChipViewData(
                $slot->localStart,
                $slot->localStart,
                $this->stateLabel($slot->state),
                $draft->appointmentTime === $slot->localStart,
            ),
            $slots,
        );

        return new TimeSelectionViewModel($this->dateStep($formConfiguration), $this->totalSteps($formConfiguration), $draft->appointmentDate, $times, $draft->appointmentTime);
    }

    public function patientDetailsViewModel(
        PublicSiteContext $context,
        ?string $patientName,
        ?string $phone,
        ?string $email,
        ?string $notes,
        bool $consent,
    ): PatientDetailsViewModel {
        $formConfiguration = $this->formConfigurationFor((string) $context->websiteId);

        return new PatientDetailsViewModel(
            $this->stepNumber(2, $formConfiguration),
            $this->totalSteps($formConfiguration),
            $patientName,
            $phone,
            $email,
            $notes,
            $consent,
            $formConfiguration->emailEnabled,
            $formConfiguration->notesEnabled,
        );
    }

    public function reviewViewModel(PublicSiteContext $context, BookingDraft $draft): ?ReviewViewModel
    {
        if ($draft->appointmentDate === null || $draft->appointmentTime === null || $draft->patientName === null || $draft->phone === null) {
            return null;
        }

        $formConfiguration = $this->formConfigurationFor((string) $context->websiteId);
        $serviceLabel = null;
        foreach ($formConfiguration->services as $service) {
            if ($service->id === $draft->serviceId) {
                $serviceLabel = $service->name;
                break;
            }
        }

        return new ReviewViewModel(
            $this->stepNumber(3, $formConfiguration),
            $this->totalSteps($formConfiguration),
            $serviceLabel,
            $draft->appointmentDate,
            $draft->appointmentTime,
            $draft->patientName,
            $draft->phone,
            $draft->email,
            $draft->notes,
        );
    }

    public function successViewModel(PublicSiteContext $context, BookingSuccessData $data): SuccessViewModel
    {
        return new SuccessViewModel(
            $data->reference,
            $data->status === 'submitted' ? 'received' : $data->status,
            $data->submittedAt->format('j M Y, g:i A'),
            $this->whatsAppUrl($context),
        );
    }

    /**
     * @throws PublicBookingValidationException
     * @throws PublicBookingBusinessRuleException
     * @throws PublicBookingAvailabilityException
     * @throws PublicBookingInfrastructureException
     */
    public function submit(string $trustedWebsiteId, BookingDraft $draft): BookingSuccessData
    {
        $tenantId = $this->tenants->forTrustedWebsite($trustedWebsiteId);

        $submission = new PublicBookingSubmission(
            tenantId: $tenantId,
            patientName: (string) $draft->patientName,
            phone: (string) $draft->phone,
            appointmentDate: (string) $draft->appointmentDate,
            appointmentTime: (string) $draft->appointmentTime,
            consent: $draft->consent,
            serviceId: $draft->serviceId,
            email: $draft->email,
            notes: $draft->notes,
        );

        $result = $this->submissions->submit($submission);

        return new BookingSuccessData($result->reference, $result->status, $result->submittedAt);
    }

    public function whatsAppUrl(PublicSiteContext $context): ?string
    {
        $website = $this->websites->find($context);

        return $website === null ? null : (new ContactActionFactory)->make($website->footer, WhatsAppDeliveryIntent::Booking)->whatsApp?->value;
    }

    public function stepNumber(int $withoutService, PublicBookingFormConfiguration $formConfiguration): int
    {
        return $formConfiguration->serviceSelectionEnabled ? $withoutService + 1 : $withoutService;
    }

    public function dateStep(PublicBookingFormConfiguration $formConfiguration): int
    {
        return $this->stepNumber(1, $formConfiguration);
    }

    public function totalSteps(PublicBookingFormConfiguration $formConfiguration): int
    {
        return $formConfiguration->serviceSelectionEnabled ? 4 : 3;
    }

    /** @param list<PublicAvailabilitySlot> $slots */
    private function aggregateState(array $slots): string
    {
        $states = array_map(static fn (PublicAvailabilitySlot $slot): PublicAvailabilityState => $slot->state, $slots);
        if (in_array(PublicAvailabilityState::Available, $states, true)) {
            return 'available';
        }

        return in_array(PublicAvailabilityState::Unknown, $states, true) ? 'unknown' : 'unavailable';
    }

    private function stateLabel(PublicAvailabilityState $state): string
    {
        return match ($state) {
            PublicAvailabilityState::Available => 'available',
            PublicAvailabilityState::Unavailable => 'unavailable',
            PublicAvailabilityState::Unknown => 'unknown',
        };
    }

    private function dateLabel(string $date, int $offset): string
    {
        return match ($offset) {
            0 => 'Today',
            1 => 'Tomorrow',
            default => (new DateTimeImmutable($date))->format('D, j M'),
        };
    }
}
