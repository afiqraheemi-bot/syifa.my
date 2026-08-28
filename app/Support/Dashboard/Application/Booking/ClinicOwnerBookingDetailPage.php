<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Booking;

use App\Modules\Booking\Contracts\Queries\BookingDetailData;
use App\Modules\Booking\Contracts\Queries\BookingHistoryData;
use App\Modules\Booking\Contracts\Queries\ClinicOwnerBookingReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\ClinicOwnerDashboardNavigation;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;

final readonly class ClinicOwnerBookingDetailPage
{
    public function __construct(
        private ClinicOwnerBookingReadInterface $bookings,
        private BookingActionProvider $actions,
    ) {}

    public function fromTrustedContext(mixed $context, string $bookingId): ?DashboardPageView
    {
        if (! $context instanceof AuthorizationContext || $context->tenantId === null) {
            throw new LogicException('Authenticated Booking detail context was not established.');
        }

        $booking = $this->bookings->detail($context->tenantId, $bookingId);
        if ($booking === null) {
            return null;
        }

        return new DashboardPageView('TenantManagement/Booking/ClinicOwnerBookingDetail', [
            'navigation' => ClinicOwnerDashboardNavigation::items('bookings'),
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => __('booking.breadcrumb_dashboard'), 'href' => route('dashboard')],
                ['key' => 'bookings', 'label' => __('booking.breadcrumb_bookings'), 'href' => route('dashboard.bookings')],
                ['key' => 'booking', 'label' => $booking->reference],
            ],
            'pageTitle' => __('booking.detail_page_title', ['reference' => $booking->reference]),
            'pageDescription' => __('booking.detail_page_description'),
            'backHref' => route('dashboard.bookings'),
            'identityName' => $context->name,
            'contextLabel' => 'Clinic Owner workspace',
            'booking' => $this->booking($booking),
            'history' => array_map($this->history(...), $this->bookings->history($context->tenantId, $bookingId)),
            'csrfToken' => csrf_token(),
        ]);
    }

    /** @return array<string, mixed> */
    private function booking(BookingDetailData $booking): array
    {
        return [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'patientName' => $booking->patientName,
            'patientPhone' => $booking->patientPhone,
            'patientEmail' => $booking->patientEmail,
            'notes' => $booking->notes,
            'serviceId' => $booking->serviceId,
            'serviceName' => $booking->serviceName,
            'source' => $booking->source,
            'sourceLabel' => $this->sourceLabel($booking->source),
            'status' => $booking->status,
            'statusLabel' => $this->statusLabel($booking->status),
            'appointmentDate' => $booking->localDate,
            'appointmentStart' => $booking->localStart,
            'appointmentEnd' => $booking->localEnd,
            'timezone' => $booking->timezone,
            'startsAtUtc' => $booking->startsAtUtc,
            'endsAtUtc' => $booking->endsAtUtc,
            'durationMinutes' => $booking->durationMinutes,
            'createdAt' => $booking->createdAt,
            'actions' => $this->actions->for($booking),
        ];
    }

    /** @return array<string, mixed> */
    private function history(BookingHistoryData $history): array
    {
        return [
            'id' => $history->id,
            'eventType' => $history->eventType,
            'eventLabel' => trim((string) preg_replace('/(?<!^)([A-Z])/', ' $1', $history->eventType)),
            'actorType' => $history->actorType,
            'actorLabel' => ucwords(str_replace('_', ' ', $history->actorType)),
            'actorId' => $history->actorId,
            'occurredAt' => $history->occurredAt,
            'payload' => $history->payload,
        ];
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'WHATSAPP' => __('booking.source_whatsapp'),
            'WALK_IN' => __('booking.source_walk_in'),
            default => ucfirst(strtolower($source)),
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => __('booking.status_awaiting_confirmation'),
            'confirmed' => __('booking.status_confirmed'),
            'cancelled' => __('booking.status_cancelled'),
            'completed' => __('booking.status_completed'),
            default => ucfirst($status),
        };
    }
}
