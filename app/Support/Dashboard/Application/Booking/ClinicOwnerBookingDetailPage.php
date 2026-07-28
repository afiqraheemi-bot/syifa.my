<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Booking;

use App\Modules\Booking\Contracts\Queries\BookingDetailData;
use App\Modules\Booking\Contracts\Queries\BookingHistoryData;
use App\Modules\Booking\Contracts\Queries\ClinicOwnerBookingReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
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
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('website', 'Website', route('dashboard.website'), false))->toArray(),
                (new DashboardNavigationItem('content', 'Content', route('dashboard.website.content'), false))->toArray(),
                (new DashboardNavigationItem('domain', 'Custom domain', route('dashboard.website.domain'), false))->toArray(),
                (new DashboardNavigationItem('bookings', 'Bookings', route('dashboard.bookings'), true))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'bookings', 'label' => 'Bookings', 'href' => route('dashboard.bookings')],
                ['key' => 'booking', 'label' => $booking->reference],
            ],
            'pageTitle' => "Booking {$booking->reference}",
            'pageDescription' => 'Review the authoritative booking details and lifecycle history.',
            'backHref' => route('dashboard.bookings'),
            'identityName' => $context->name,
            'contextLabel' => 'SYIFA.my workspace',
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
            'statusLabel' => ucfirst($booking->status),
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
            'WHATSAPP' => 'WhatsApp',
            'WALK_IN' => 'Walk-in',
            default => ucfirst(strtolower($source)),
        };
    }
}
