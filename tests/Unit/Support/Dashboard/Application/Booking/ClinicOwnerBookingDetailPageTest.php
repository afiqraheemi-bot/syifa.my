<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Application\Booking;

use App\Modules\Booking\Contracts\Queries\BookingDetailData;
use App\Modules\Booking\Contracts\Queries\BookingHistoryData;
use App\Modules\Booking\Contracts\Queries\ClinicOwnerBookingReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\Booking\BookingActionProvider;
use App\Support\Dashboard\Application\Booking\ClinicOwnerBookingDetailPage;
use Tests\TestCase;

final class ClinicOwnerBookingDetailPageTest extends TestCase
{
    public function test_it_projects_nullable_service_manual_source_and_actor_as_separate_evidence(): void
    {
        $page = (new ClinicOwnerBookingDetailPage(
            $this->reader($this->booking()),
            new BookingActionProvider,
        ))->fromTrustedContext($this->context(), $this->booking()->id);

        self::assertNotNull($page);
        self::assertNull($page->props['booking']['serviceName']);
        self::assertSame('WhatsApp', $page->props['booking']['sourceLabel']);
        self::assertSame('clinic_owner', $page->props['history'][0]['actorType']);
        self::assertSame('owner-1', $page->props['history'][0]['actorId']);
        self::assertSame('WHATSAPP', $page->props['history'][0]['payload']['source']);
        self::assertSame(['confirm', 'reschedule', 'cancel'], array_column($page->props['booking']['actions'], 'key'));
    }

    public function test_it_returns_no_page_when_tenant_scoped_reader_cannot_find_booking(): void
    {
        $page = (new ClinicOwnerBookingDetailPage(
            $this->reader(null),
            new BookingActionProvider,
        ))->fromTrustedContext($this->context(), '00000000-0000-4000-8000-000000000099');

        self::assertNull($page);
    }

    private function context(): AuthorizationContext
    {
        return new AuthorizationContext('clinic_owner', 'owner-1', 'tenant-1', 'clinic_owner', 'Aisyah', 'shared.authenticated-route', []);
    }

    private function booking(): BookingDetailData
    {
        return new BookingDetailData(
            '00000000-0000-4000-8000-000000000001',
            'tenant-1',
            null,
            null,
            'BOOK-001',
            'WHATSAPP',
            'submitted',
            'Patient Name',
            '+6012',
            null,
            'Call before arrival',
            '2026-09-01',
            '09:00',
            '09:30',
            'Asia/Kuala_Lumpur',
            '2026-09-01T01:00:00Z',
            '2026-09-01T01:30:00Z',
            30,
            '2026-08-31T01:00:00Z',
        );
    }

    private function reader(?BookingDetailData $booking): ClinicOwnerBookingReadInterface
    {
        return new class($booking) implements ClinicOwnerBookingReadInterface
        {
            public function __construct(private readonly ?BookingDetailData $booking) {}

            public function detail(string $trustedTenantId, string $bookingId): ?BookingDetailData
            {
                return $this->booking;
            }

            public function list(string $trustedTenantId, ?string $status, ?string $cursor, int $limit, ?string $search = null, ?string $source = null): array
            {
                return [];
            }

            public function countByStatus(string $trustedTenantId): array
            {
                return [];
            }

            public function countBySource(string $trustedTenantId): array
            {
                return [];
            }

            public function history(string $trustedTenantId, string $bookingId): array
            {
                if ($this->booking === null) {
                    return [];
                }

                return [new BookingHistoryData(
                    'history-1',
                    'BookingSubmitted',
                    'clinic_owner',
                    'owner-1',
                    '2026-08-31T01:00:00Z',
                    ['source' => 'WHATSAPP'],
                )];
            }
        };
    }
}
