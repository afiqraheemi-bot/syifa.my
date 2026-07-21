<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence\Repositories;

use App\Modules\Booking\Contracts\Repositories\BookingRepositoryInterface;
use App\Modules\Booking\Domain\Booking;
use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;
use App\Modules\Booking\Domain\Exceptions\StaleBookingWriteException;
use App\Modules\Booking\Domain\ValueObjects\BookingId;
use App\Modules\Booking\Infrastructure\Persistence\Exceptions\InvalidBookingStorageStateException;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\BookingPersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Records\BookingStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final class PostgresBookingRepository implements BookingRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly BookingPersistenceMapper $mapper,
    ) {}

    public function findById(BookingId $bookingId): ?Booking
    {
        $row = $this->connection->table('bookings')
            ->where('id', $bookingId->value)
            ->first();

        return $row === null ? null : $this->bookingFromRow($row);
    }

    public function findByReference(string $reference): ?Booking
    {
        $row = $this->connection->table('bookings')
            ->where('booking_reference', $reference)
            ->first();

        return $row === null ? null : $this->bookingFromRow($row);
    }

    public function save(Booking $booking): void
    {
        $newVersion = $this->connection->transaction(
            fn (): int => $booking->version() === 0
                ? $this->insert($booking)
                : $this->update($booking),
        );

        $booking->synchronizeVersion($newVersion);
    }

    private function insert(Booking $booking): int
    {
        $record = $this->mapper->record($booking);
        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $this->connection->table('bookings')->insert([
            ...$this->payload($record, 1),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return 1;
    }

    private function update(Booking $booking): int
    {
        $record = $this->mapper->record($booking);
        $newVersion = $record->version + 1;
        $affected = $this->connection->table('bookings')
            ->where('id', $record->id)
            ->where('version', $record->version)
            ->update([
                ...$this->payload($record, $newVersion),
                'updated_at' => $this->databaseTimestamp(new DateTimeImmutable),
            ]);

        if ($affected !== 1) {
            throw new StaleBookingWriteException('Booking write rejected because its version is stale.');
        }

        return $newVersion;
    }

    /** @return array<string, mixed> */
    private function payload(BookingStorageRecord $record, int $version): array
    {
        return [
            'id' => $record->id,
            'tenant_id' => $record->tenantId,
            'clinic_id' => $record->clinicId,
            'booking_reference' => $record->bookingReference,
            'status' => $record->status,
            'patient_name' => $record->patientName,
            'patient_phone' => $record->patientPhone,
            'patient_email' => $record->patientEmail,
            'appointment_on' => $record->appointmentOn,
            'appointment_time' => $record->appointmentTime,
            'notes' => $record->notes,
            'domain_created_at' => $this->databaseTimestamp($record->domainCreatedAt),
            'domain_updated_at' => $this->databaseTimestamp($record->domainUpdatedAt),
            'version' => $version,
        ];
    }

    private function bookingFromRow(stdClass $row): Booking
    {
        $record = new BookingStorageRecord(
            $this->stringValue($row, 'id'),
            $this->stringValue($row, 'tenant_id'),
            $this->stringValue($row, 'clinic_id'),
            $this->stringValue($row, 'booking_reference'),
            $this->stringValue($row, 'status'),
            $this->stringValue($row, 'patient_name'),
            $this->stringValue($row, 'patient_phone'),
            $this->nullableStringValue($row, 'patient_email'),
            $this->stringValue($row, 'appointment_on'),
            $this->timeOnlyValue($row, 'appointment_time'),
            $this->nullableStringValue($row, 'notes'),
            $this->dateTimeValue($row->domain_created_at ?? null, 'domain_created_at'),
            $this->dateTimeValue($row->domain_updated_at ?? null, 'domain_updated_at'),
            $this->integerValue($row, 'version'),
        );

        try {
            return $this->mapper->toDomain($record);
        } catch (InvalidBookingValueException $exception) {
            throw new InvalidBookingStorageStateException('Stored booking row failed Domain validation.', 0, $exception);
        }
    }

    private function stringValue(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value)) {
            throw new InvalidBookingStorageStateException(sprintf('Storage field %s must be a string.', $field));
        }

        return $value;
    }

    private function nullableStringValue(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        if ($value === null || is_string($value)) {
            return $value;
        }

        throw new InvalidBookingStorageStateException(sprintf('Storage field %s must be a string or null.', $field));
    }

    private function integerValue(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new InvalidBookingStorageStateException(sprintf('Storage field %s must be an integer.', $field));
    }

    private function timeOnlyValue(stdClass $row, string $field): string
    {
        $value = $this->stringValue($row, $field);

        return substr($value, 0, 5);
    }

    private function dateTimeValue(mixed $value, string $field): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        throw new InvalidBookingStorageStateException(sprintf('Storage field %s must be a timestamp.', $field));
    }

    private function databaseTimestamp(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s.uP');
    }
}
