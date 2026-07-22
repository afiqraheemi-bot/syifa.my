<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence\Repositories;

use App\Modules\Booking\Contracts\Repositories\BookingFormConfigurationRepositoryInterface;
use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\Exceptions\InvalidBookingFormConfigurationValueException;
use App\Modules\Booking\Domain\Exceptions\StaleBookingFormConfigurationWriteException;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\Booking\Infrastructure\Persistence\Exceptions\InvalidBookingFormConfigurationStorageStateException;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\BookingFormConfigurationPersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Records\BookingFormConfigurationStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use JsonException;
use stdClass;

final class PostgresBookingFormConfigurationRepository implements BookingFormConfigurationRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly BookingFormConfigurationPersistenceMapper $mapper,
    ) {}

    public function findByTenant(TenantId $tenantId): ?BookingFormConfiguration
    {
        $row = $this->connection->table('booking_form_configurations')
            ->where('tenant_id', $tenantId->value)
            ->first();

        return $row === null ? null : $this->configurationFromRow($row);
    }

    public function save(BookingFormConfiguration $configuration): void
    {
        $newVersion = $this->connection->transaction(
            fn (): int => $configuration->version() === 0
                ? $this->insert($configuration)
                : $this->update($configuration),
        );

        $configuration->synchronizeVersion($newVersion);
    }

    private function insert(BookingFormConfiguration $configuration): int
    {
        $record = $this->mapper->record($configuration);
        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $this->connection->table('booking_form_configurations')->insert([
            ...$this->payload($record, 1),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return 1;
    }

    private function update(BookingFormConfiguration $configuration): int
    {
        $record = $this->mapper->record($configuration);
        $newVersion = $record->version + 1;
        $affected = $this->connection->table('booking_form_configurations')
            ->where('tenant_id', $record->tenantId)
            ->where('version', $record->version)
            ->update([
                ...$this->payload($record, $newVersion),
                'updated_at' => $this->databaseTimestamp(new DateTimeImmutable),
            ]);

        if ($affected !== 1) {
            throw new StaleBookingFormConfigurationWriteException('Booking form configuration write rejected because its version is stale.');
        }

        return $newVersion;
    }

    /** @return array<string, mixed> */
    private function payload(BookingFormConfigurationStorageRecord $record, int $version): array
    {
        return [
            'tenant_id' => $record->tenantId,
            'enable_service_selection' => $record->enableServiceSelection,
            'enable_doctor_selection' => $record->enableDoctorSelection,
            'enable_email' => $record->enableEmail,
            'enable_branch' => $record->enableBranch,
            'enable_notes' => $record->enableNotes,
            'required_fields' => $this->encodeJson($record->requiredFields),
            'field_order' => $this->encodeJson($record->fieldOrder),
            'field_labels' => $this->encodeJson($record->fieldLabels),
            'domain_created_at' => $this->databaseTimestamp($record->domainCreatedAt),
            'domain_updated_at' => $this->databaseTimestamp($record->domainUpdatedAt),
            'version' => $version,
        ];
    }

    private function configurationFromRow(stdClass $row): BookingFormConfiguration
    {
        $record = new BookingFormConfigurationStorageRecord(
            $this->stringValue($row, 'tenant_id'),
            $this->boolValue($row, 'enable_service_selection'),
            $this->boolValue($row, 'enable_doctor_selection'),
            $this->boolValue($row, 'enable_email'),
            $this->boolValue($row, 'enable_branch'),
            $this->boolValue($row, 'enable_notes'),
            $this->decodeJsonList($row, 'required_fields'),
            $this->decodeJsonList($row, 'field_order'),
            $this->decodeJsonMap($row, 'field_labels'),
            $this->dateTimeValue($row->domain_created_at ?? null, 'domain_created_at'),
            $this->dateTimeValue($row->domain_updated_at ?? null, 'domain_updated_at'),
            $this->integerValue($row, 'version'),
        );

        try {
            return $this->mapper->toDomain($record);
        } catch (InvalidBookingFormConfigurationValueException $exception) {
            throw new InvalidBookingFormConfigurationStorageStateException('Stored booking form configuration row failed Domain validation.', 0, $exception);
        }
    }

    private function encodeJson(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidBookingFormConfigurationStorageStateException('Booking form configuration payload could not be encoded.', 0, $exception);
        }
    }

    /** @return list<string> */
    private function decodeJsonList(stdClass $row, string $field): array
    {
        $decoded = $this->decodeJson($row, $field);

        if (! is_array($decoded)) {
            throw new InvalidBookingFormConfigurationStorageStateException(sprintf('Storage field %s must decode to an array.', $field));
        }

        return array_values(array_map(static fn (mixed $value): string => (string) $value, $decoded));
    }

    /** @return array<string, string> */
    private function decodeJsonMap(stdClass $row, string $field): array
    {
        $decoded = $this->decodeJson($row, $field);

        if (! is_array($decoded)) {
            throw new InvalidBookingFormConfigurationStorageStateException(sprintf('Storage field %s must decode to an array.', $field));
        }

        $map = [];
        foreach ($decoded as $key => $value) {
            $map[(string) $key] = (string) $value;
        }

        return $map;
    }

    private function decodeJson(stdClass $row, string $field): mixed
    {
        $value = $this->stringValue($row, $field);

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidBookingFormConfigurationStorageStateException(sprintf('Storage field %s must be valid JSON.', $field), 0, $exception);
        }
    }

    private function stringValue(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value)) {
            throw new InvalidBookingFormConfigurationStorageStateException(sprintf('Storage field %s must be a string.', $field));
        }

        return $value;
    }

    private function boolValue(stdClass $row, string $field): bool
    {
        $value = $row->{$field} ?? null;

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 't' || $value === '1' || $value === 1) {
            return true;
        }

        if ($value === 'f' || $value === '0' || $value === 0) {
            return false;
        }

        throw new InvalidBookingFormConfigurationStorageStateException(sprintf('Storage field %s must be a boolean.', $field));
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

        throw new InvalidBookingFormConfigurationStorageStateException(sprintf('Storage field %s must be an integer.', $field));
    }

    private function dateTimeValue(mixed $value, string $field): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        throw new InvalidBookingFormConfigurationStorageStateException(sprintf('Storage field %s must be a timestamp.', $field));
    }

    private function databaseTimestamp(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s.uP');
    }
}
