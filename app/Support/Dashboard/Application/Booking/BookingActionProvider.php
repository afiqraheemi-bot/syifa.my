<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Booking;

use App\Modules\Booking\Contracts\Queries\BookingDetailData;

final readonly class BookingActionProvider
{
    /** @return list<array{key: string, label: string, href: string, method: string, requiresSchedule: bool, confirmation: ?string, tone: string}> */
    public function for(BookingDetailData $booking): array
    {
        $actions = [];
        if ($booking->status === 'submitted') {
            $actions[] = [
                'key' => 'confirm',
                'label' => 'Confirm',
                'href' => route('dashboard.bookings.confirm', ['bookingId' => $booking->id]),
                'method' => 'post',
                'requiresSchedule' => false,
                'confirmation' => 'Confirm this booking?',
                'tone' => 'primary',
            ];
        }
        if (in_array($booking->status, ['submitted', 'confirmed'], true)) {
            $actions[] = [
                'key' => 'reschedule',
                'label' => 'Reschedule',
                'href' => route('dashboard.bookings.reschedule', ['bookingId' => $booking->id]),
                'method' => 'patch',
                'requiresSchedule' => true,
                'confirmation' => 'Save this new appointment date and time?',
                'tone' => 'neutral',
            ];
            $actions[] = [
                'key' => 'cancel',
                'label' => 'Cancel',
                'href' => route('dashboard.bookings.cancel', ['bookingId' => $booking->id]),
                'method' => 'post',
                'requiresSchedule' => false,
                'confirmation' => 'Cancel this booking? This action cannot be undone.',
                'tone' => 'danger',
            ];
        }

        return $actions;
    }
}
