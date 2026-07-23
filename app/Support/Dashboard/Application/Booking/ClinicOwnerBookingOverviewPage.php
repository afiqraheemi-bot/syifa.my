<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Booking;

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
    ) {}

    /** @param array<string, mixed> $query */
    public function fromTrustedContext(mixed $context, array $query): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Authenticated Booking dashboard context was not established.');
        }

        $criteria = BookingOverviewCriteria::fromInput($query);

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
        ]);
    }
}
