<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Booking;

use App\Modules\Booking\Contracts\Queries\ClinicOwnerBookingReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use LogicException;

final readonly class BookingSourceSummaryProvider
{
    public function __construct(private ClinicOwnerBookingReadInterface $bookings) {}

    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        if ($context->tenantId === null) {
            throw new LogicException('Booking source summary requires a trusted Tenant identifier.');
        }

        $counts = $this->bookings->countBySource($context->tenantId);

        return new DashboardSectionProjection('bookingSourceSummary', [
            'items' => array_map(
                static fn (array $option): array => [
                    'key' => $option['value'],
                    'label' => $option['label'],
                    'count' => $counts[$option['value']] ?? 0,
                ],
                BookingOverviewCriteria::sourceOptions(),
            ),
        ]);
    }
}
