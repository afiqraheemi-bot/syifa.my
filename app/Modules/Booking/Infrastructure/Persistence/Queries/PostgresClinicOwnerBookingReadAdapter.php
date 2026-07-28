<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence\Queries;

use App\Modules\Booking\Contracts\Queries\BookingDetailData;
use App\Modules\Booking\Contracts\Queries\BookingHistoryData;
use App\Modules\Booking\Contracts\Queries\ClinicOwnerBookingReadInterface;
use App\Modules\Booking\Infrastructure\Persistence\Exceptions\InvalidBookingStorageStateException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use JsonException;
use stdClass;

final readonly class PostgresClinicOwnerBookingReadAdapter implements ClinicOwnerBookingReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function detail(string $trustedTenantId, string $bookingId): ?BookingDetailData
    {
        $row = $this->query()
            ->where('bookings.tenant_id', $trustedTenantId)
            ->where('bookings.id', $bookingId)
            ->first();

        return $row === null ? null : $this->detailData($row);
    }

    public function list(
        string $trustedTenantId,
        ?string $status,
        ?string $cursor,
        int $limit,
        ?string $search = null,
        ?string $source = null,
    ): array {
        $query = $this->query()
            ->where('bookings.tenant_id', $trustedTenantId)
            ->orderBy('bookings.id')
            ->limit(max(1, min(100, $limit)));
        if ($status !== null) {
            $query->where('bookings.status', $status);
        }
        if ($source !== null) {
            $query->where('bookings.booking_source', $source);
        }
        if ($search !== null) {
            $query->where('bookings.booking_reference', 'ilike', '%'.$search.'%');
        }
        if ($cursor !== null) {
            $query->where('bookings.id', '>', $cursor);
        }

        return array_values(array_map(fn (stdClass $row): BookingDetailData => $this->detailData($row), $query->get()->all()));
    }

    public function countByStatus(string $trustedTenantId): array
    {
        return $this->groupedCounts($trustedTenantId, 'status');
    }

    public function countBySource(string $trustedTenantId): array
    {
        return $this->groupedCounts($trustedTenantId, 'booking_source');
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
        foreach (['id', 'tenant_id', 'booking_reference', 'booking_source', 'status', 'patient_name', 'patient_phone', 'appointment_on', 'appointment_time', 'local_end_time', 'timezone', 'starts_at_utc', 'ends_at_utc', 'appointment_duration_minutes', 'domain_created_at'] as $field) {
            if (! isset($row->{$field})) {
                throw new InvalidBookingStorageStateException(sprintf('Booking read field %s is missing.', $field));
            }
        }

        return new BookingDetailData(
            (string) $row->id,
            (string) $row->tenant_id,
            isset($row->service_id) ? (string) $row->service_id : null,
            isset($row->service_name) ? (string) $row->service_name : null,
            (string) $row->booking_reference,
            (string) $row->booking_source,
            (string) $row->status,
            (string) $row->patient_name,
            (string) $row->patient_phone,
            isset($row->patient_email) ? (string) $row->patient_email : null,
            isset($row->notes) ? (string) $row->notes : null,
            substr((string) $row->appointment_on, 0, 10),
            substr((string) $row->appointment_time, 0, 5),
            substr((string) $row->local_end_time, 0, 5),
            (string) $row->timezone,
            (string) $row->starts_at_utc,
            (string) $row->ends_at_utc,
            (int) $row->appointment_duration_minutes,
            (string) $row->domain_created_at,
        );
    }

    private function query(): Builder
    {
        return $this->connection->table('bookings')
            ->leftJoin('services', function ($join): void {
                $join->on('services.id', '=', 'bookings.service_id')
                    ->on('services.tenant_id', '=', 'bookings.tenant_id');
            })
            ->select('bookings.*', 'services.name as service_name');
    }

    /** @return array<string, int> */
    private function groupedCounts(string $trustedTenantId, string $column): array
    {
        $counts = [];
        foreach ($this->connection->table('bookings')
            ->where('tenant_id', $trustedTenantId)
            ->selectRaw($column.', COUNT(*) AS aggregate')
            ->groupBy($column)
            ->get() as $row) {
            $counts[(string) $row->{$column}] = (int) $row->aggregate;
        }

        ksort($counts);

        return $counts;
    }
}
