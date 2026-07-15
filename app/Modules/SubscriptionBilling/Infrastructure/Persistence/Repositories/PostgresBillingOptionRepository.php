<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories;

use App\Modules\SubscriptionBilling\Contracts\Repositories\BillingOptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\BillingOption;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionId;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\InvalidCommercialCatalogueStorageStateException;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\StaleCommercialCatalogueWriteException;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\CommercialCataloguePersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\BillingOptionStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final class PostgresBillingOptionRepository implements BillingOptionRepositoryInterface
{
    private const string CURRENT_TABLE = 'commercial_catalogue_billing_options';

    private const string HISTORY_TABLE = 'commercial_catalogue_billing_option_versions';

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly CommercialCataloguePersistenceMapper $mapper,
    ) {}

    public function findById(BillingOptionId $billingOptionId): ?BillingOption
    {
        $row = $this->connection->table(self::CURRENT_TABLE)
            ->where('id', $billingOptionId->value)
            ->first();

        return $row === null ? null : $this->mapper->toBillingOptionDomain($this->billingOptionRecordFromRow($row));
    }

    public function findByCode(BillingOptionCode $billingOptionCode): ?BillingOption
    {
        $row = $this->connection->table(self::CURRENT_TABLE)
            ->where('code', $billingOptionCode->value)
            ->first();

        return $row === null ? null : $this->mapper->toBillingOptionDomain($this->billingOptionRecordFromRow($row));
    }

    public function existsByCode(BillingOptionCode $billingOptionCode): bool
    {
        return $this->connection->table(self::CURRENT_TABLE)
            ->where('code', $billingOptionCode->value)
            ->exists();
    }

    public function save(BillingOption $billingOption): void
    {
        $persistedVersion = $this->connection->transaction(function () use ($billingOption): ?int {
            $currentRow = $this->connection->table(self::CURRENT_TABLE)
                ->where('id', $billingOption->id->value)
                ->lockForUpdate()
                ->first();

            $record = $this->mapper->billingOptionRecord($billingOption);

            if ($currentRow === null) {
                if ($billingOption->version() !== 0) {
                    throw new StaleCommercialCatalogueWriteException('Billing Option already exists with a different version.');
                }

                $this->insertCurrentAndHistory($record, 1);

                return 1;
            }

            $current = $this->billingOptionRecordFromRow($currentRow);

            if ($current->version !== $billingOption->version()) {
                throw new StaleCommercialCatalogueWriteException(
                    'Billing Option write rejected because its version is stale.',
                );
            }

            if ($current->code !== $record->code) {
                throw new InvalidCommercialCatalogueStorageStateException(
                    'Billing Option code cannot be rewritten.',
                );
            }

            if ($this->sameBillingOption($current, $record)) {
                return null;
            }

            $newVersion = $current->version + 1;
            $this->updateCurrent($record, $newVersion);
            $this->insertHistory($record, $newVersion);

            return $newVersion;
        });

        if ($persistedVersion !== null) {
            $billingOption->synchronizeVersion($persistedVersion);
        }
    }

    private function insertCurrentAndHistory(BillingOptionStorageRecord $record, int $version): void
    {
        $this->insertCurrent($record, $version);
        $this->insertHistory($record, $version);
    }

    private function insertCurrent(BillingOptionStorageRecord $record, int $version): void
    {
        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $this->connection->table(self::CURRENT_TABLE)->insert([
            'id' => $record->id,
            'code' => $record->code,
            'name' => $record->name,
            'availability' => $record->availability,
            'recurrence_classification' => $record->recurrenceClassification,
            'interval_unit' => $record->intervalUnit,
            'interval_count' => $record->intervalCount,
            'effective_start' => $this->databaseDate($record->effectiveStart),
            'effective_end' => $record->effectiveEnd === null ? null : $this->databaseDate($record->effectiveEnd),
            'display_order' => $record->displayOrder,
            'version' => $version,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function updateCurrent(BillingOptionStorageRecord $record, int $version): void
    {
        $affected = $this->connection->table(self::CURRENT_TABLE)
            ->where('id', $record->id)
            ->where('version', $record->version)
            ->update([
                'code' => $record->code,
                'name' => $record->name,
                'availability' => $record->availability,
                'recurrence_classification' => $record->recurrenceClassification,
                'interval_unit' => $record->intervalUnit,
                'interval_count' => $record->intervalCount,
                'effective_start' => $this->databaseDate($record->effectiveStart),
                'effective_end' => $record->effectiveEnd === null ? null : $this->databaseDate($record->effectiveEnd),
                'display_order' => $record->displayOrder,
                'version' => $version,
                'updated_at' => $this->databaseTimestamp(new DateTimeImmutable),
            ]);

        if ($affected !== 1) {
            throw new StaleCommercialCatalogueWriteException(
                'Billing Option write rejected because its version is stale.',
            );
        }
    }

    private function insertHistory(BillingOptionStorageRecord $record, int $version): void
    {
        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $this->connection->table(self::HISTORY_TABLE)->insert([
            'id' => $record->id,
            'version' => $version,
            'code' => $record->code,
            'name' => $record->name,
            'availability' => $record->availability,
            'recurrence_classification' => $record->recurrenceClassification,
            'interval_unit' => $record->intervalUnit,
            'interval_count' => $record->intervalCount,
            'effective_start' => $this->databaseDate($record->effectiveStart),
            'effective_end' => $record->effectiveEnd === null ? null : $this->databaseDate($record->effectiveEnd),
            'display_order' => $record->displayOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function sameBillingOption(BillingOptionStorageRecord $current, BillingOptionStorageRecord $record): bool
    {
        return $current->code === $record->code
            && $current->name === $record->name
            && $current->availability === $record->availability
            && $current->recurrenceClassification === $record->recurrenceClassification
            && $current->intervalUnit === $record->intervalUnit
            && $current->intervalCount === $record->intervalCount
            && $current->displayOrder === $record->displayOrder
            && $this->dateTimesEqual($current->effectiveStart, $record->effectiveStart)
            && $this->dateTimesEqual($current->effectiveEnd, $record->effectiveEnd);
    }

    private function billingOptionRecordFromRow(stdClass $row): BillingOptionStorageRecord
    {
        return new BillingOptionStorageRecord(
            $this->stringValue($row, 'id'),
            $this->stringValue($row, 'code'),
            $this->stringValue($row, 'name'),
            $this->stringValue($row, 'availability'),
            $this->stringValue($row, 'recurrence_classification'),
            $this->nullableStringValue($row, 'interval_unit'),
            $this->nullableIntegerValue($row, 'interval_count'),
            $this->dateValue($row->effective_start ?? null, 'effective_start'),
            $this->nullableDateValue($row->effective_end ?? null, 'effective_end'),
            $this->integerValue($row, 'display_order'),
            $this->integerValue($row, 'version'),
        );
    }

    private function stringValue(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value)) {
            throw new InvalidCommercialCatalogueStorageStateException(sprintf('Billing Option storage field %s must be a string.', $field));
        }

        return $value;
    }

    private function nullableStringValue(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidCommercialCatalogueStorageStateException(sprintf('Billing Option storage field %s must be a string or null.', $field));
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

        throw new InvalidCommercialCatalogueStorageStateException(sprintf('Billing Option storage field %s must be an integer.', $field));
    }

    private function nullableIntegerValue(stdClass $row, string $field): ?int
    {
        $value = $row->{$field} ?? null;

        if ($value === null) {
            return null;
        }

        return $this->integerValue($row, $field);
    }

    private function dateValue(mixed $value, string $field): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        throw new InvalidCommercialCatalogueStorageStateException(sprintf('Billing Option storage field %s must be a date.', $field));
    }

    private function nullableDateValue(mixed $value, string $field): ?DateTimeImmutable
    {
        return $value === null ? null : $this->dateValue($value, $field);
    }

    private function dateTimesEqual(?DateTimeInterface $left, ?DateTimeInterface $right): bool
    {
        if ($left === null || $right === null) {
            return $left === null && $right === null;
        }

        return $left->format('Y-m-d') === $right->format('Y-m-d');
    }

    private function databaseDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    private function databaseTimestamp(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s.uP');
    }
}
