<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence\Repositories;

use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Domain\Exceptions\InvalidServiceValueException;
use App\Modules\Booking\Domain\Exceptions\StaleServiceWriteException;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\Booking\Infrastructure\Persistence\Exceptions\InvalidServiceStorageStateException;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\ServicePersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Records\ServiceStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final class PostgresServiceRepository implements ServiceRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly ServicePersistenceMapper $mapper,
    ) {}

    public function findById(ServiceId $serviceId): ?Service
    {
        $row = $this->connection->table('services')
            ->where('id', $serviceId->value)
            ->first();

        return $row === null ? null : $this->serviceFromRow($row);
    }

    /** @return list<Service> */
    public function findAll(TenantId $tenantId): array
    {
        $rows = $this->connection->table('services')
            ->where('tenant_id', $tenantId->value)
            ->orderBy('sort_order')
            ->get();

        return array_values(array_map(
            fn (stdClass $row): Service => $this->serviceFromRow($row),
            $rows->all(),
        ));
    }

    /** @return list<Service> */
    public function findActive(TenantId $tenantId): array
    {
        $rows = $this->connection->table('services')
            ->where('tenant_id', $tenantId->value)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get();

        return array_values(array_map(
            fn (stdClass $row): Service => $this->serviceFromRow($row),
            $rows->all(),
        ));
    }

    public function existsByName(TenantId $tenantId, string $name): bool
    {
        return $this->connection->table('services')
            ->where('tenant_id', $tenantId->value)
            ->where('name', $name)
            ->exists();
    }

    public function save(Service $service): void
    {
        $newVersion = $this->connection->transaction(
            fn (): int => $service->version() === 0
                ? $this->insert($service)
                : $this->update($service),
        );

        $service->synchronizeVersion($newVersion);
    }

    private function insert(Service $service): int
    {
        $record = $this->mapper->record($service);
        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $this->connection->table('services')->insert([
            ...$this->payload($record, 1),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return 1;
    }

    private function update(Service $service): int
    {
        $record = $this->mapper->record($service);
        $newVersion = $record->version + 1;
        $affected = $this->connection->table('services')
            ->where('id', $record->id)
            ->where('version', $record->version)
            ->update([
                ...$this->payload($record, $newVersion),
                'updated_at' => $this->databaseTimestamp(new DateTimeImmutable),
            ]);

        if ($affected !== 1) {
            throw new StaleServiceWriteException('Service write rejected because its version is stale.');
        }

        return $newVersion;
    }

    /** @return array<string, mixed> */
    private function payload(ServiceStorageRecord $record, int $version): array
    {
        return [
            'id' => $record->id,
            'tenant_id' => $record->tenantId,
            'name' => $record->name,
            'description' => $record->description,
            'duration_minutes' => $record->durationMinutes,
            'sort_order' => $record->sortOrder,
            'status' => $record->status,
            'domain_created_at' => $this->databaseTimestamp($record->domainCreatedAt),
            'domain_updated_at' => $this->databaseTimestamp($record->domainUpdatedAt),
            'version' => $version,
        ];
    }

    private function serviceFromRow(stdClass $row): Service
    {
        $record = new ServiceStorageRecord(
            $this->stringValue($row, 'id'),
            $this->stringValue($row, 'tenant_id'),
            $this->stringValue($row, 'name'),
            $this->nullableStringValue($row, 'description'),
            $this->nullableIntegerValue($row, 'duration_minutes'),
            $this->integerValue($row, 'sort_order'),
            $this->stringValue($row, 'status'),
            $this->dateTimeValue($row->domain_created_at ?? null, 'domain_created_at'),
            $this->dateTimeValue($row->domain_updated_at ?? null, 'domain_updated_at'),
            $this->integerValue($row, 'version'),
        );

        try {
            return $this->mapper->toDomain($record);
        } catch (InvalidServiceValueException $exception) {
            throw new InvalidServiceStorageStateException('Stored service row failed Domain validation.', 0, $exception);
        }
    }

    private function stringValue(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value)) {
            throw new InvalidServiceStorageStateException(sprintf('Storage field %s must be a string.', $field));
        }

        return $value;
    }

    private function nullableStringValue(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        if ($value === null || is_string($value)) {
            return $value;
        }

        throw new InvalidServiceStorageStateException(sprintf('Storage field %s must be a string or null.', $field));
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

        throw new InvalidServiceStorageStateException(sprintf('Storage field %s must be an integer.', $field));
    }

    private function nullableIntegerValue(stdClass $row, string $field): ?int
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

        throw new InvalidServiceStorageStateException(sprintf('Storage field %s must be an integer or null.', $field));
    }

    private function dateTimeValue(mixed $value, string $field): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        throw new InvalidServiceStorageStateException(sprintf('Storage field %s must be a timestamp.', $field));
    }

    private function databaseTimestamp(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s.uP');
    }
}
