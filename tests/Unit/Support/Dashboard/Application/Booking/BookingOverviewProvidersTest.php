<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Application\Booking;

use App\Modules\Booking\Contracts\Queries\BookingDetailData;
use App\Modules\Booking\Contracts\Queries\ClinicOwnerBookingReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\Booking\BookingActionProvider;
use App\Support\Dashboard\Application\Booking\BookingListProvider;
use App\Support\Dashboard\Application\Booking\BookingOverviewCriteria;
use App\Support\Dashboard\Application\Booking\BookingSourceSummaryProvider;
use App\Support\Dashboard\Application\Booking\BookingStatusSummaryProvider;
use Tests\TestCase;

final class BookingOverviewProvidersTest extends TestCase
{
    public function test_list_projects_booking_query_data_and_cursor_contract(): void
    {
        $provider = new BookingListProvider($this->reader([
            $this->booking('booking-1', 'BOOK-001', 'WEBSITE', 'submitted'),
            $this->booking('booking-2', 'BOOK-002', 'PHONE', 'confirmed'),
            $this->booking('booking-3', 'BOOK-003', 'WALK_IN', 'completed'),
        ]), new BookingActionProvider);

        $projection = $provider->provide(
            $this->context(),
            new BookingOverviewCriteria('BOOK', null, null, null, 2),
        )->data;

        self::assertCount(2, $projection['items']);
        self::assertSame('BOOK-001', $projection['items'][0]['reference']);
        self::assertSame('Website', $projection['items'][0]['sourceLabel']);
        self::assertSame(['confirm', 'reschedule', 'cancel'], array_column($projection['items'][0]['actions'], 'key'));
        self::assertSame(['reschedule', 'cancel'], array_column($projection['items'][1]['actions'], 'key'));
        self::assertSame(['primary', 'neutral', 'danger'], array_column($projection['items'][0]['actions'], 'tone'));
        self::assertSame(
            'Cancel this booking? This action cannot be undone.',
            $projection['items'][0]['actions'][2]['confirmation'],
        );
        self::assertSame('booking-2', $projection['pagination']['nextCursor']);
        self::assertTrue($projection['pagination']['hasMore']);
        self::assertStringContainsString('cursor=booking-2', $projection['pagination']['nextHref']);
        self::assertSame('BOOK', $projection['search']['value']);
    }

    public function test_status_and_source_providers_return_complete_zero_safe_summaries(): void
    {
        $reader = $this->reader([], ['submitted' => 3, 'confirmed' => 1], ['WEBSITE' => 2, 'PHONE' => 2]);

        $statuses = (new BookingStatusSummaryProvider($reader))->provide($this->context())->data;
        $sources = (new BookingSourceSummaryProvider($reader))->provide($this->context())->data;

        self::assertSame(4, $statuses['total']);
        self::assertSame([3, 1, 0, 0], array_column($statuses['items'], 'count'));
        self::assertSame([2, 0, 2, 0, 0], array_column($sources['items'], 'count'));
    }

    public function test_query_contract_normalizes_search_filters_and_pagination(): void
    {
        $criteria = BookingOverviewCriteria::fromInput([
            'search' => '  BOOK-100  ',
            'status' => 'confirmed',
            'source' => 'PHONE',
            'cursor' => 'booking-10',
            'per_page' => '500',
        ]);

        self::assertSame('BOOK-100', $criteria->search);
        self::assertSame('confirmed', $criteria->status);
        self::assertSame('PHONE', $criteria->source);
        self::assertSame('booking-10', $criteria->cursor);
        self::assertSame(50, $criteria->perPage);

        $invalid = BookingOverviewCriteria::fromInput(['status' => 'unknown', 'source' => 'OTHER']);
        self::assertNull($invalid->status);
        self::assertNull($invalid->source);
        self::assertSame(20, $invalid->perPage);
    }

    public function test_empty_query_result_projects_an_explicit_empty_list(): void
    {
        $projection = (new BookingListProvider($this->reader([]), new BookingActionProvider))
            ->provide($this->context(), BookingOverviewCriteria::fromInput([]))
            ->data;

        self::assertSame([], $projection['items']);
        self::assertFalse($projection['pagination']['hasMore']);
        self::assertNull($projection['pagination']['nextHref']);
    }

    public function test_terminal_bookings_expose_no_operation_actions(): void
    {
        $provider = new BookingListProvider($this->reader([
            $this->booking('booking-1', 'BOOK-001', 'WEBSITE', 'cancelled'),
            $this->booking('booking-2', 'BOOK-002', 'PHONE', 'completed'),
        ]), new BookingActionProvider);

        $items = $provider->provide(
            $this->context(),
            new BookingOverviewCriteria(null, null, null, null, 20),
        )->data['items'];

        self::assertSame([], $items[0]['actions']);
        self::assertSame([], $items[1]['actions']);
    }

    private function context(): AuthorizationContext
    {
        return new AuthorizationContext('clinic_owner', 'owner-1', 'tenant-1', 'clinic_owner', 'Aisyah', 'shared.authenticated-route', []);
    }

    private function booking(string $id, string $reference, string $source, string $status): BookingDetailData
    {
        return new BookingDetailData(
            $id, 'tenant-1', 'service-1', 'Consultation', $reference, $source, $status,
            'Patient Name', '+6012', 'patient@example.test', 'Booking notes',
            '2026-09-01', '09:00', '09:30', 'Asia/Kuala_Lumpur',
            '2026-09-01T01:00:00Z', '2026-09-01T01:30:00Z', 30, '2026-08-31T01:00:00Z',
        );
    }

    /**
     * @param  list<BookingDetailData>  $rows
     * @param  array<string, int>  $statuses
     * @param  array<string, int>  $sources
     */
    private function reader(array $rows, array $statuses = [], array $sources = []): ClinicOwnerBookingReadInterface
    {
        return new class($rows, $statuses, $sources) implements ClinicOwnerBookingReadInterface
        {
            /**
             * @param  list<BookingDetailData>  $rows
             * @param  array<string, int>  $statuses
             * @param  array<string, int>  $sources
             */
            public function __construct(
                private readonly array $rows,
                private readonly array $statuses,
                private readonly array $sources,
            ) {}

            public function detail(string $trustedTenantId, string $bookingId): ?BookingDetailData
            {
                return null;
            }

            public function list(string $trustedTenantId, ?string $status, ?string $cursor, int $limit, ?string $search = null, ?string $source = null): array
            {
                return array_slice($this->rows, 0, $limit);
            }

            public function countByStatus(string $trustedTenantId): array
            {
                return $this->statuses;
            }

            public function countBySource(string $trustedTenantId): array
            {
                return $this->sources;
            }

            public function history(string $trustedTenantId, string $bookingId): array
            {
                return [];
            }
        };
    }
}
