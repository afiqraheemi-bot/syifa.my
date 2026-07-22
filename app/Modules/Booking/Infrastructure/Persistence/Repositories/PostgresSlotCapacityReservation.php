<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence\Repositories;

use App\Modules\Booking\Application\Exceptions\SlotUnavailableException;
use App\Modules\Booking\Contracts\Capacity\ReservationSlotData;
use App\Modules\Booking\Contracts\Capacity\SlotCapacityReservationInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

final readonly class PostgresSlotCapacityReservation implements SlotCapacityReservationInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function reserve(string $tenantId, ReservationSlotData $slot, int $capacity): void
    {
        $now = $this->timestamp(new DateTimeImmutable);
        $this->connection->table('booking_slot_reservation_buckets')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'starts_at_utc' => $this->timestamp($slot->startsAtUtc),
            'ends_at_utc' => $this->timestamp($slot->endsAtUtc),
            'capacity_snapshot' => $capacity,
            'reserved_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $bucket = $this->bucket($tenantId, $slot, true);
        if ($bucket === null || $bucket->reservedCount >= $bucket->capacitySnapshot) {
            throw new SlotUnavailableException('The requested appointment slot is unavailable.');
        }

        $this->connection->table('booking_slot_reservation_buckets')
            ->where('tenant_id', $tenantId)
            ->where('starts_at_utc', $this->timestamp($slot->startsAtUtc))
            ->where('ends_at_utc', $this->timestamp($slot->endsAtUtc))
            ->update(['reserved_count' => $bucket->reservedCount + 1, 'updated_at' => $now]);
    }

    public function release(string $tenantId, ReservationSlotData $slot): void
    {
        $bucket = $this->bucket($tenantId, $slot, true);
        if ($bucket === null || $bucket->reservedCount < 1) {
            throw new RuntimeException('Reserved slot capacity cannot be released more than once.');
        }
        $this->connection->table('booking_slot_reservation_buckets')
            ->where('tenant_id', $tenantId)
            ->where('starts_at_utc', $this->timestamp($slot->startsAtUtc))
            ->where('ends_at_utc', $this->timestamp($slot->endsAtUtc))
            ->update([
                'reserved_count' => $bucket->reservedCount - 1,
                'updated_at' => $this->timestamp(new DateTimeImmutable),
            ]);
    }

    public function isAvailable(string $tenantId, ReservationSlotData $slot): bool
    {
        $bucket = $this->bucket($tenantId, $slot, false);

        return $bucket === null || $bucket->reservedCount < $bucket->capacitySnapshot;
    }

    private function bucket(string $tenantId, ReservationSlotData $slot, bool $lock): ?SlotCapacityBucketState
    {
        $query = $this->connection->table('booking_slot_reservation_buckets')
            ->where('tenant_id', $tenantId)
            ->where('starts_at_utc', $this->timestamp($slot->startsAtUtc))
            ->where('ends_at_utc', $this->timestamp($slot->endsAtUtc));
        if ($lock) {
            $query->lockForUpdate();
        }

        $row = $query->first();
        if ($row === null) {
            return null;
        }

        $reserved = $row->reserved_count ?? null;
        $capacity = $row->capacity_snapshot ?? null;
        if (! is_int($reserved) && ! (is_string($reserved) && ctype_digit($reserved))) {
            throw new RuntimeException('Stored reservation occupancy is invalid.');
        }
        if (! is_int($capacity) && ! (is_string($capacity) && ctype_digit($capacity))) {
            throw new RuntimeException('Stored reservation capacity is invalid.');
        }

        return new SlotCapacityBucketState((int) $reserved, (int) $capacity);
    }

    private function timestamp(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s.uP');
    }
}

final readonly class SlotCapacityBucketState
{
    public function __construct(public int $reservedCount, public int $capacitySnapshot) {}
}
