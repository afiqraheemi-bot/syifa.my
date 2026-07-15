<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories;

use App\Modules\SubscriptionBilling\Contracts\Repositories\CapabilityDefinitionRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CapabilityKey;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\CapabilityDefinition;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityId;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\InvalidCommercialCatalogueStorageStateException;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\StaleCommercialCatalogueWriteException;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\CommercialCataloguePersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\CapabilityDefinitionStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final class PostgresCapabilityDefinitionRepository implements CapabilityDefinitionRepositoryInterface
{
    private const string CURRENT_TABLE = 'commercial_catalogue_capabilities';

    private const string HISTORY_TABLE = 'commercial_catalogue_capability_versions';

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly CommercialCataloguePersistenceMapper $mapper,
    ) {}

    public function findById(CapabilityId $capabilityId): ?CapabilityDefinition
    {
        $row = $this->connection->table(self::CURRENT_TABLE)
            ->where('id', $capabilityId->value)
            ->first();

        return $row === null ? null : $this->mapper->toCapabilityDefinitionDomain($this->definitionRecordFromRow($row));
    }

    public function findByKey(CapabilityKey $capabilityKey): ?CapabilityDefinition
    {
        $row = $this->connection->table(self::CURRENT_TABLE)
            ->where('key', $capabilityKey->value)
            ->first();

        return $row === null ? null : $this->mapper->toCapabilityDefinitionDomain($this->definitionRecordFromRow($row));
    }

    public function save(CapabilityDefinition $capabilityDefinition): void
    {
        $persistedVersion = $this->connection->transaction(function () use ($capabilityDefinition): ?int {
            $currentRow = $this->connection->table(self::CURRENT_TABLE)
                ->where('id', $capabilityDefinition->id->value)
                ->lockForUpdate()
                ->first();

            $record = $this->mapper->capabilityDefinitionRecord($capabilityDefinition);

            if ($currentRow === null) {
                if ($capabilityDefinition->version() !== 0) {
                    throw new StaleCommercialCatalogueWriteException(
                        'Capability Definition already exists with a different version.',
                    );
                }

                $this->insertCurrentAndHistory($record, 1);

                return 1;
            }

            $current = $this->definitionRecordFromRow($currentRow);

            if ($current->version !== $capabilityDefinition->version()) {
                throw new StaleCommercialCatalogueWriteException(
                    'Capability Definition write rejected because its version is stale.',
                );
            }

            if ($current->key !== $record->key) {
                throw new InvalidCommercialCatalogueStorageStateException(
                    'Capability Definition key cannot be rewritten.',
                );
            }

            if ($this->sameCapabilityDefinition($current, $record)) {
                return null;
            }

            $newVersion = $current->version + 1;
            $this->updateCurrent($record, $newVersion);
            $this->insertHistory($record, $newVersion);

            return $newVersion;
        });

        if ($persistedVersion !== null) {
            $capabilityDefinition->synchronizeVersion($persistedVersion);
        }
    }

    public function existsByKey(CapabilityKey $capabilityKey): bool
    {
        return $this->connection->table(self::CURRENT_TABLE)
            ->where('key', $capabilityKey->value)
            ->exists();
    }

    private function insertCurrentAndHistory(CapabilityDefinitionStorageRecord $record, int $version): void
    {
        $this->insertCurrent($record, $version);
        $this->insertHistory($record, $version);
    }

    private function insertCurrent(CapabilityDefinitionStorageRecord $record, int $version): void
    {
        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $this->connection->table(self::CURRENT_TABLE)->insert([
            'id' => $record->id,
            'key' => $record->key,
            'name' => $record->name,
            'description' => $record->description,
            'commercial_meaning' => $record->commercialMeaning,
            'status' => $record->status,
            'version' => $version,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function updateCurrent(CapabilityDefinitionStorageRecord $record, int $version): void
    {
        $affected = $this->connection->table(self::CURRENT_TABLE)
            ->where('id', $record->id)
            ->where('version', $record->version)
            ->update([
                'key' => $record->key,
                'name' => $record->name,
                'description' => $record->description,
                'commercial_meaning' => $record->commercialMeaning,
                'status' => $record->status,
                'version' => $version,
                'updated_at' => $this->databaseTimestamp(new DateTimeImmutable),
            ]);

        if ($affected !== 1) {
            throw new StaleCommercialCatalogueWriteException(
                'Capability Definition write rejected because its version is stale.',
            );
        }
    }

    private function insertHistory(CapabilityDefinitionStorageRecord $record, int $version): void
    {
        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $this->connection->table(self::HISTORY_TABLE)->insert([
            'id' => $record->id,
            'version' => $version,
            'key' => $record->key,
            'name' => $record->name,
            'description' => $record->description,
            'commercial_meaning' => $record->commercialMeaning,
            'status' => $record->status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function sameCapabilityDefinition(
        CapabilityDefinitionStorageRecord $current,
        CapabilityDefinitionStorageRecord $record,
    ): bool {
        return $current->key === $record->key
            && $current->name === $record->name
            && $current->description === $record->description
            && $current->commercialMeaning === $record->commercialMeaning
            && $current->status === $record->status;
    }

    private function definitionRecordFromRow(stdClass $row): CapabilityDefinitionStorageRecord
    {
        return new CapabilityDefinitionStorageRecord(
            $this->stringValue($row, 'id'),
            $this->stringValue($row, 'key'),
            $this->stringValue($row, 'name'),
            $this->stringValue($row, 'description'),
            $this->stringValue($row, 'commercial_meaning'),
            $this->stringValue($row, 'status'),
            $this->integerValue($row, 'version'),
        );
    }

    private function stringValue(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value)) {
            throw new InvalidCommercialCatalogueStorageStateException(sprintf('Capability storage field %s must be a string.', $field));
        }

        return $value;
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

        throw new InvalidCommercialCatalogueStorageStateException(sprintf('Capability storage field %s must be an integer.', $field));
    }

    private function databaseTimestamp(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s.uP');
    }
}
