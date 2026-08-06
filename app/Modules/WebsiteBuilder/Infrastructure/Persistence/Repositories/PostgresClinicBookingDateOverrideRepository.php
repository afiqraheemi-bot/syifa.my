<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories;

use App\Modules\WebsiteBuilder\Application\ClinicBooking\ClinicBookingDateOverrideData;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicBookingDateOverrideRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleClinicWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresClinicBookingDateOverrideRepository implements ClinicBookingDateOverrideRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function allForClinic(ClinicId $clinicId): array
    {
        $rows = $this->connection->table('clinic_booking_date_overrides')
            ->where('clinic_id', $clinicId->value)
            ->orderBy('local_date')
            ->get();

        return array_values($rows->map(fn (object $row): ClinicBookingDateOverrideData => $this->data(
            $clinicId,
            (string) $row->local_date,
            (bool) $row->is_closed,
            (int) $row->version,
        ))->all());
    }

    public function replace(ClinicId $clinicId, string $localDate, bool $closed, array $intervals, int $expectedVersion): ClinicBookingDateOverrideData
    {
        return $this->connection->transaction(function () use ($clinicId, $localDate, $closed, $intervals, $expectedVersion): ClinicBookingDateOverrideData {
            $now = new DateTimeImmutable;
            if ($expectedVersion === 0) {
                $inserted = $this->connection->table('clinic_booking_date_overrides')->insertOrIgnore([
                    'clinic_id' => $clinicId->value,
                    'local_date' => $localDate,
                    'is_closed' => $closed,
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                if ($inserted !== 1) {
                    throw new StaleClinicWriteException('Booking date override already exists.');
                }
                $version = 1;
            } else {
                $version = $expectedVersion + 1;
                $updated = $this->connection->table('clinic_booking_date_overrides')
                    ->where('clinic_id', $clinicId->value)
                    ->where('local_date', $localDate)
                    ->where('version', $expectedVersion)
                    ->update(['is_closed' => $closed, 'version' => $version, 'updated_at' => $now]);
                if ($updated !== 1) {
                    throw new StaleClinicWriteException('Booking date override changed since it was loaded.');
                }
                $this->connection->table('clinic_booking_date_override_intervals')
                    ->where('clinic_id', $clinicId->value)
                    ->where('local_date', $localDate)
                    ->delete();
            }

            if (! $closed) {
                $this->connection->table('clinic_booking_date_override_intervals')->insert(array_map(
                    static fn (array $interval, int $position): array => [
                        'clinic_id' => $clinicId->value,
                        'local_date' => $localDate,
                        'position' => $position,
                        'starts_at' => $interval['opens_at'],
                        'ends_at' => $interval['closes_at'],
                    ],
                    $intervals,
                    array_keys($intervals),
                ));
            }

            return new ClinicBookingDateOverrideData($localDate, $closed, $closed ? [] : $intervals, $version);
        });
    }

    public function delete(ClinicId $clinicId, string $localDate, int $expectedVersion): void
    {
        $deleted = $this->connection->table('clinic_booking_date_overrides')
            ->where('clinic_id', $clinicId->value)
            ->where('local_date', $localDate)
            ->where('version', $expectedVersion)
            ->delete();
        if ($deleted !== 1) {
            throw new StaleClinicWriteException('Booking date override changed since it was loaded.');
        }
    }

    private function data(ClinicId $clinicId, string $localDate, bool $closed, int $version): ClinicBookingDateOverrideData
    {
        $intervals = array_values($this->connection->table('clinic_booking_date_override_intervals')
            ->where('clinic_id', $clinicId->value)
            ->where('local_date', $localDate)
            ->orderBy('position')
            ->get()
            ->map(static fn (object $row): array => [
                'opens_at' => substr((string) $row->starts_at, 0, 5),
                'closes_at' => substr((string) $row->ends_at, 0, 5),
            ])->all());

        return new ClinicBookingDateOverrideData($localDate, $closed, $intervals, $version);
    }
}
