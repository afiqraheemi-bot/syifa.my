<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Booking;

use App\Modules\Booking\Contracts\Queries\PublicBookingFormReaderInterface;
use App\Modules\Booking\Contracts\Queries\PublicBookingFormServiceData;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;

final readonly class ClinicOwnerBookingOverviewPage
{
    public function __construct(
        private BookingListProvider $list,
        private BookingStatusSummaryProvider $statuses,
        private BookingSourceSummaryProvider $sources,
        private PublicBookingFormReaderInterface $bookingForm,
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

        return new DashboardPageView('TenantManagement/Booking/ClinicOwnerBookingOverview', [
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('website', 'Website', route('dashboard.website'), false))->toArray(),
                (new DashboardNavigationItem('content', 'Content', route('dashboard.website.content'), false))->toArray(),
                (new DashboardNavigationItem('bookings', 'Bookings', route('dashboard.bookings'), true))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'bookings', 'label' => 'Bookings'],
            ],
            'pageTitle' => 'Bookings',
            'pageDescription' => 'Review appointments submitted to your clinic.',
            'identityName' => $context->name,
            'contextLabel' => 'SYIFA.my workspace',
            'bookingList' => $this->list->provide($context, $criteria)->data,
            'statusSummary' => $this->statuses->provide($context)->data,
            'sourceSummary' => $this->sources->provide($context)->data,
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
