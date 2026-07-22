<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Queries;

interface ClinicOwnerBookingReadInterface
{
    public function detail(string $trustedTenantId, string $bookingId): ?BookingDetailData;

    /** @return list<BookingDetailData> */
    public function list(string $trustedTenantId, ?string $status, ?string $cursor, int $limit): array;

    /** @return list<BookingHistoryData> */
    public function history(string $trustedTenantId, string $bookingId): array;
}
