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
                'label' => __('booking.action_confirm'),
                'href' => route('dashboard.bookings.confirm', ['bookingId' => $booking->id]),
                'method' => 'post',
                'requiresSchedule' => false,
                'confirmation' => __('booking.action_confirm_confirmation'),
                'tone' => 'primary',
            ];
        }
        if ($booking->status === 'confirmed') {
            $actions[] = [
                'key' => 'complete',
                'label' => __('booking.action_complete'),
                'href' => route('dashboard.bookings.complete', ['bookingId' => $booking->id]),
                'method' => 'post',
                'requiresSchedule' => false,
                'confirmation' => __('booking.action_complete_confirmation'),
                'tone' => 'primary',
            ];
        }
        if (in_array($booking->status, ['submitted', 'confirmed'], true)) {
            $actions[] = [
                'key' => 'reschedule',
                'label' => __('booking.action_reschedule'),
                'href' => route('dashboard.bookings.reschedule', ['bookingId' => $booking->id]),
                'method' => 'patch',
                'requiresSchedule' => true,
                'confirmation' => __('booking.action_reschedule_confirmation'),
                'tone' => 'neutral',
            ];
            $actions[] = [
                'key' => 'cancel',
                'label' => __('booking.action_cancel'),
                'href' => route('dashboard.bookings.cancel', ['bookingId' => $booking->id]),
                'method' => 'post',
                'requiresSchedule' => false,
                'confirmation' => __('booking.action_cancel_confirmation'),
                'tone' => 'danger',
            ];
        }

        return $actions;
    }
}
