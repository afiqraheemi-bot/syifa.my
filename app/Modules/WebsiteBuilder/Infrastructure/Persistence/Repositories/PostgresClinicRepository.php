<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories;

use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Clinic;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicOperationalTimeException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleClinicWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Exceptions\InvalidClinicStorageStateException;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\ClinicPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records\ClinicStorageRecord;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records\OperatingIntervalStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final readonly class PostgresClinicRepository implements ClinicRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClinicPersistenceMapper $mapper,
    ) {}

    public function findById(TenantId $tenantId, ClinicId $clinicId): ?Clinic
    {
        $row = $this->connection->table('clinics')
            ->where('id', $clinicId->value)
            ->where('tenant_id', $tenantId->value)
            ->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function findByTenantId(TenantId $tenantId): ?Clinic
    {
        $row = $this->connection->table('clinics')->where('tenant_id', $tenantId->value)->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function save(Clinic $clinic): void
    {
        $newVersion = $this->connection->transaction(function () use ($clinic): int {
            $record = $this->mapper->toRecord($clinic);
            $newVersion = $record->version + 1;

            if ($record->version === 0) {
                $this->insertClinic($record, $newVersion);
            } else {
                $affected = $this->connection->table('clinics')
                    ->where('id', $record->id)
                    ->where('tenant_id', $record->tenantId)
                    ->where('version', $record->version)
                    ->update([
                        'timezone' => $record->timezone,
                        'appointment_duration_minutes' => $record->appointmentDurationMinutes,
                        'booking_capacity_per_slot' => $record->bookingCapacityPerSlot,
                        'domain_updated_at' => $this->timestamp($record->domainUpdatedAt),
                        'version' => $newVersion,
                        'updated_at' => $this->timestamp(new DateTimeImmutable),
                    ]);
                if ($affected !== 1) {
                    throw new StaleClinicWriteException('Clinic write rejected because its version is stale.');
                }
                $this->connection->table('clinic_operating_intervals')->where('clinic_id', $record->id)->delete();
                $this->connection->table('clinic_booking_availability_intervals')->where('clinic_id', $record->id)->delete();
            }

            $this->insertIntervals($record);
            $this->insertBookingAvailability($record);
            $this->saveContactProfile($record);

            return $newVersion;
        });

        $clinic->synchronizeVersion($newVersion);
    }

    private function insertClinic(ClinicStorageRecord $record, int $version): void
    {
        $now = $this->timestamp(new DateTimeImmutable);
        $this->connection->table('clinics')->insert([
            'id' => $record->id,
            'tenant_id' => $record->tenantId,
            'timezone' => $record->timezone,
            'appointment_duration_minutes' => $record->appointmentDurationMinutes,
            'booking_capacity_per_slot' => $record->bookingCapacityPerSlot,
            'domain_created_at' => $this->timestamp($record->domainCreatedAt),
            'domain_updated_at' => $this->timestamp($record->domainUpdatedAt),
            'version' => $version,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertIntervals(ClinicStorageRecord $record): void
    {
        if ($record->operatingIntervals === []) {
            return;
        }

        $this->connection->table('clinic_operating_intervals')->insert(array_map(
            static fn (OperatingIntervalStorageRecord $interval): array => [
                'clinic_id' => $record->id,
                'day_of_week' => $interval->dayOfWeek,
                'opens_at' => $interval->opensAt,
                'closes_at' => $interval->closesAt,
            ],
            $record->operatingIntervals,
        ));
    }

    private function insertBookingAvailability(ClinicStorageRecord $record): void
    {
        if ($record->bookingAvailabilityIntervals === []) {
            return;
        }

        $this->connection->table('clinic_booking_availability_intervals')->insert(array_map(
            static fn (OperatingIntervalStorageRecord $interval): array => [
                'clinic_id' => $record->id,
                'day_of_week' => $interval->dayOfWeek,
                'starts_at' => $interval->opensAt,
                'ends_at' => $interval->closesAt,
            ],
            $record->bookingAvailabilityIntervals,
        ));
    }

    private function saveContactProfile(ClinicStorageRecord $record): void
    {
        $now = $this->timestamp(new DateTimeImmutable);
        $values = [
            'tenant_id' => $record->tenantId,
            'operational_phone' => $record->operationalPhone,
            'operational_email' => $record->operationalEmail,
            'postal_address' => $record->postalAddress,
            'whatsapp_number' => $record->whatsAppNumber,
            'latitude' => $record->latitude,
            'longitude' => $record->longitude,
            'updated_at' => $now,
        ];
        $query = $this->connection->table('clinic_contact_profiles')->where('clinic_id', $record->id);
        if ($query->exists()) {
            $query->update($values);

            return;
        }
        $this->connection->table('clinic_contact_profiles')->insert([
            'clinic_id' => $record->id,
            ...$values,
            'created_at' => $now,
        ]);
    }

    private function toDomain(stdClass $row): Clinic
    {
        $id = $this->string($row, 'id');
        $intervalRows = $this->connection->table('clinic_operating_intervals')
            ->where('clinic_id', $id)
            ->orderBy('day_of_week')
            ->orderBy('opens_at')
            ->get();

        $intervals = array_values(array_map(fn (stdClass $interval): OperatingIntervalStorageRecord => new OperatingIntervalStorageRecord(
            $this->integer($interval, 'day_of_week'),
            $this->time($interval, 'opens_at'),
            $this->time($interval, 'closes_at'),
        ), $intervalRows->all()));

        $bookingIntervalRows = $this->connection->table('clinic_booking_availability_intervals')
            ->where('clinic_id', $id)
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get();
        $bookingIntervals = array_values(array_map(fn (stdClass $interval): OperatingIntervalStorageRecord => new OperatingIntervalStorageRecord(
            $this->integer($interval, 'day_of_week'),
            $this->time($interval, 'starts_at'),
            $this->time($interval, 'ends_at'),
        ), $bookingIntervalRows->all()));

        $profile = $this->connection->table('clinic_contact_profiles')
            ->where('clinic_id', $id)
            ->where('tenant_id', $this->string($row, 'tenant_id'))
            ->first();
        if ($profile === null) {
            throw new InvalidClinicStorageStateException('Stored Clinic is missing its Contact Profile.');
        }

        $record = new ClinicStorageRecord(
            $id,
            $this->string($row, 'tenant_id'),
            $this->string($row, 'timezone'),
            $intervals,
            $bookingIntervals,
            $this->dateTime($row, 'domain_created_at'),
            $this->dateTime($row, 'domain_updated_at'),
            $this->integer($row, 'version'),
            $this->nullableInteger($row, 'appointment_duration_minutes'),
            $this->nullableInteger($row, 'booking_capacity_per_slot'),
            $this->nullableString($profile, 'operational_phone'),
            $this->nullableString($profile, 'operational_email'),
            $this->nullableString($profile, 'postal_address'),
            $this->nullableString($profile, 'whatsapp_number'),
            $this->nullableFloat($profile, 'latitude'),
            $this->nullableFloat($profile, 'longitude'),
        );

        try {
            return $this->mapper->toDomain($record);
        } catch (InvalidClinicOperationalTimeException $exception) {
            throw new InvalidClinicStorageStateException('Stored Clinic operational time failed Domain validation.', 0, $exception);
        }
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidClinicStorageStateException(sprintf('Storage field %s must be a non-empty string.', $field));
        }

        return $value;
    }

    private function integer(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }
        throw new InvalidClinicStorageStateException(sprintf('Storage field %s must be an integer.', $field));
    }

    private function nullableInteger(stdClass $row, string $field): ?int
    {
        $value = $row->{$field} ?? null;
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }
        throw new InvalidClinicStorageStateException(sprintf('Storage field %s must be an integer or null.', $field));
    }

    private function nullableString(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;
        if ($value === null || is_string($value)) {
            return $value;
        }
        throw new InvalidClinicStorageStateException(sprintf('Storage field %s must be a string or null.', $field));
    }

    private function nullableFloat(stdClass $row, string $field): ?float
    {
        $value = $row->{$field} ?? null;
        if ($value === null) {
            return null;
        }
        if (is_float($value) || is_int($value) || is_numeric($value)) {
            return (float) $value;
        }
        throw new InvalidClinicStorageStateException(sprintf('Storage field %s must be numeric or null.', $field));
    }

    private function time(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;
        if (! is_string($value)) {
            throw new InvalidClinicStorageStateException(sprintf('Storage field %s must be a time.', $field));
        }

        return substr($value, 0, 5);
    }

    private function dateTime(stdClass $row, string $field): DateTimeImmutable
    {
        $value = $row->{$field} ?? null;
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }
        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }
        throw new InvalidClinicStorageStateException(sprintf('Storage field %s must be a timestamp.', $field));
    }

    private function timestamp(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s.uP');
    }
}
