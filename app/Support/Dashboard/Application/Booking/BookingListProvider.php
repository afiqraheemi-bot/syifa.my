<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Booking;

use App\Modules\Booking\Contracts\Queries\BookingDetailData;
use App\Modules\Booking\Contracts\Queries\ClinicOwnerBookingReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use LogicException;

final readonly class BookingListProvider
{
    public function __construct(
        private ClinicOwnerBookingReadInterface $bookings,
        private BookingActionProvider $actions,
    ) {}

    public function provide(
        AuthorizationContext $context,
        BookingOverviewCriteria $criteria,
    ): DashboardSectionProjection {
        if ($context->tenantId === null) {
            throw new LogicException('Booking overview requires a trusted Tenant identifier.');
        }

        $rows = $this->bookings->list(
            $context->tenantId,
            $criteria->status,
            $criteria->cursor,
            $criteria->perPage + 1,
            $criteria->search,
            $criteria->source,
        );
        $hasMore = count($rows) > $criteria->perPage;
        $visible = array_slice($rows, 0, $criteria->perPage);
        $last = $visible === [] ? null : $visible[array_key_last($visible)];
        $nextCursor = $hasMore && $last instanceof BookingDetailData ? $last->id : null;

        return new DashboardSectionProjection('bookingList', [
            'items' => array_map($this->project(...), $visible),
            'pagination' => [
                'cursor' => $criteria->cursor,
                'nextCursor' => $nextCursor,
                'nextHref' => $nextCursor === null ? null : route('dashboard.bookings', array_filter([
                    'search' => $criteria->search,
                    'status' => $criteria->status,
                    'source' => $criteria->source,
                    'cursor' => $nextCursor,
                    'per_page' => $criteria->perPage,
                ], static fn (string|int|null $value): bool => $value !== null)),
                'perPage' => $criteria->perPage,
                'hasMore' => $hasMore,
            ],
            'search' => [
                'action' => route('dashboard.bookings'),
                'value' => $criteria->search,
                'placeholder' => 'Search booking reference',
            ],
            'filters' => [
                'status' => ['value' => $criteria->status, 'options' => BookingOverviewCriteria::statusOptions()],
                'source' => ['value' => $criteria->source, 'options' => BookingOverviewCriteria::sourceOptions()],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function project(BookingDetailData $booking): array
    {
        return [
            'id' => $booking->id,
            'detailHref' => route('dashboard.bookings.show', ['bookingId' => $booking->id]),
            'reference' => $booking->reference,
            'serviceId' => $booking->serviceId,
            'source' => $booking->source,
            'sourceLabel' => $this->sourceLabel($booking->source),
            'status' => $booking->status,
            'statusLabel' => ucfirst($booking->status),
            'appointmentDate' => $booking->localDate,
            'appointmentStart' => $booking->localStart,
            'appointmentEnd' => $booking->localEnd,
            'timezone' => $booking->timezone,
            'durationMinutes' => $booking->durationMinutes,
            'actions' => $this->actions->for($booking),
        ];
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'WHATSAPP' => 'WhatsApp',
            'WALK_IN' => 'Walk-in',
            default => ucfirst(strtolower($source)),
        };
    }
}
