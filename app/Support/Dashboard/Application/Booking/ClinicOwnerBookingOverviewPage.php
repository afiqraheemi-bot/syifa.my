<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Booking;

use App\Modules\Booking\Contracts\Queries\PublicBookingFormReaderInterface;
use App\Modules\Booking\Contracts\Queries\PublicBookingFormServiceData;
use App\Modules\WebsiteBuilder\Application\ClinicBooking\ManageClinicBookingDateOverridesService;
use App\Modules\WebsiteBuilder\Application\ClinicBooking\ManageClinicBookingScheduleService;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\ClinicOwnerDashboardNavigation;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;

final readonly class ClinicOwnerBookingOverviewPage
{
    public function __construct(
        private BookingListProvider $list,
        private BookingStatusSummaryProvider $statuses,
        private BookingSourceSummaryProvider $sources,
        private PublicBookingFormReaderInterface $bookingForm,
        private ManageClinicBookingScheduleService $bookingSchedule,
        private ManageClinicBookingDateOverridesService $dateOverrides,
    ) {}

    /** @param array<string, mixed> $query */
    public function fromTrustedContext(mixed $context, array $query): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Authenticated Booking dashboard context was not established.');
        }

        $criteria = BookingOverviewCriteria::fromInput($query);
        if ($context->tenantId === null) {
            throw new LogicException('Booking overview requires a trusted Tenant identifier.');
        }
        $bookingForm = $this->bookingForm->forTrustedTenant($context->tenantId);
        $bookingSchedule = $this->bookingSchedule->read(
            $context->tenantId,
            new WebsiteAuthorizationContext(
                $context->identityId,
                $context->role,
                actorTenantId: $context->tenantId,
            ),
        );
        $dateOverrides = $this->dateOverrides->read(
            $context->tenantId,
            new WebsiteAuthorizationContext(
                $context->identityId,
                $context->role,
                actorTenantId: $context->tenantId,
            ),
        );

        return new DashboardPageView('TenantManagement/Booking/ClinicOwnerBookingOverview', [
            'navigation' => ClinicOwnerDashboardNavigation::items('bookings'),
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'bookings', 'label' => 'Bookings'],
            ],
            'pageTitle' => 'Bookings',
            'pageDescription' => 'Review appointments submitted to your clinic.',
            'identityName' => $context->name,
            'contextLabel' => 'Clinic Owner workspace',
            'bookingList' => $this->list->provide($context, $criteria)->data,
            'statusSummary' => $this->statuses->provide($context)->data,
            'sourceSummary' => $this->sources->provide($context)->data,
            'bookingSchedule' => [
                ...$bookingSchedule->toArray(),
                'updateUrl' => route('dashboard.bookings.schedule.update'),
                'businessHoursUpdateUrl' => route('dashboard.bookings.business-hours.update'),
                'dateOverrideStoreUrl' => route('dashboard.bookings.date-overrides.store'),
                'dateOverrideDeleteUrlTemplate' => route('dashboard.bookings.date-overrides.destroy', ['localDate' => '__DATE__']),
                'dateOverrides' => array_map(static fn ($override): array => $override->toArray(), $dateOverrides),
            ],
            'manualBooking' => [
                'storeUrl' => route('dashboard.bookings.store'),
                'sources' => [
                    ['value' => 'phone', 'label' => 'Phone'],
                    ['value' => 'whatsapp', 'label' => 'WhatsApp'],
                    ['value' => 'walk_in', 'label' => 'Walk-in'],
                    ['value' => 'staff', 'label' => 'Staff'],
                ],
                'serviceSelectionEnabled' => $bookingForm->serviceSelectionEnabled,
                'serviceSelectionRequired' => $bookingForm->serviceSelectionRequired,
                'emailEnabled' => $bookingForm->emailEnabled,
                'notesEnabled' => $bookingForm->notesEnabled,
                'services' => array_map(static fn (PublicBookingFormServiceData $service): array => [
                    'id' => $service->id,
                    'name' => $service->name,
                ], $bookingForm->services),
            ],
        ]);
    }
}
