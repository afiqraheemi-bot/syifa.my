<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence\Queries;

use App\Modules\Booking\Contracts\Queries\BookingDetailData;
use App\Modules\Booking\Contracts\Queries\BookingHistoryData;
use App\Modules\Booking\Contracts\Queries\ClinicOwnerBookingReadInterface;
use App\Modules\Booking\Infrastructure\Persistence\Exceptions\InvalidBookingStorageStateException;
use Illuminate\Database\ConnectionInterface;
use JsonException;
use stdClass;

final readonly class PostgresClinicOwnerBookingReadAdapter implements ClinicOwnerBookingReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function detail(string $trustedTenantId, string $bookingId): ?BookingDetailData
    {
        $row = $this->connection->table('bookings')->where('tenant_id', $trustedTenantId)->where('id', $bookingId)->first();

        return $row === null ? null : $this->detailData($row);
    }

    public function list(string $trustedTenantId, ?string $status, ?string $cursor, int $limit): array
    {
        $query = $this->connection->table('bookings')->where('tenant_id', $trustedTenantId)->orderBy('id')->limit(max(1, min(100, $limit)));
        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($cursor !== null) {
            $query->where('id', '>', $cursor);
        }

        return array_values(array_map(fn (stdClass $row): BookingDetailData => $this->detailData($row), $query->get()->all()));
    }

    public function history(string $trustedTenantId, string $bookingId): array
    {
        if ($this->detail($trustedTenantId, $bookingId) === null) {
            return [];
        }

        return array_values(array_map(function (stdClass $row): BookingHistoryData {
            try {
                $payload = json_decode((string) $row->payload, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidBookingStorageStateException('Stored Booking history payload is invalid.', 0, $exception);
            }
            if (! is_array($payload)) {
                throw new InvalidBookingStorageStateException('Stored Booking history payload must be an object.');
            }

            return new BookingHistoryData((string) $row->id, (string) $row->event_type, (string) $row->actor_type, isset($row->actor_id) ? (string) $row->actor_id : null, (string) $row->occurred_at, $payload);
        }, $this->connection->table('booking_history')->where('tenant_id', $trustedTenantId)->where('booking_id', $bookingId)->orderBy('occurred_at')->orderBy('id')->get()->all()));
    }

    private function detailData(stdClass $row): BookingDetailData
    {
        foreach (['id', 'tenant_id', 'service_id', 'booking_reference', 'status', 'appointment_on', 'appointment_time', 'local_end_time', 'timezone', 'starts_at_utc', 'ends_at_utc', 'appointment_duration_minutes'] as $field) {
            if (! isset($row->{$field})) {
                throw new InvalidBookingStorageStateException(sprintf('Booking read field %s is missing.', $field));
            }
        }

        return new BookingDetailData((string) $row->id, (string) $row->tenant_id, (string) $row->service_id, (string) $row->booking_reference, (string) $row->status, substr((string) $row->appointment_on, 0, 10), substr((string) $row->appointment_time, 0, 5), substr((string) $row->local_end_time, 0, 5), (string) $row->timezone, (string) $row->starts_at_utc, (string) $row->ends_at_utc, (int) $row->appointment_duration_minutes);
    }
}
