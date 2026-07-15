<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories;

use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanOfferingRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\PlanOffering;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingId;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\InvalidCommercialCatalogueStorageStateException;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\StaleCommercialCatalogueWriteException;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\CommercialCataloguePersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\PlanOfferingStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;
use Traversable;

final class PostgresPlanOfferingRepository implements PlanOfferingRepositoryInterface
{
    private const string CURRENT_TABLE = 'commercial_catalogue_plan_offerings';

    private const string HISTORY_TABLE = 'commercial_catalogue_plan_offering_versions';

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly CommercialCataloguePersistenceMapper $mapper,
    ) {}

    public function findById(PlanOfferingId $planOfferingId): ?PlanOffering
    {
        $row = $this->connection->table(self::CURRENT_TABLE)
            ->where('id', $planOfferingId->value)
            ->first();

        return $row === null ? null : $this->mapper->toPlanOfferingDomain($this->planOfferingRecordFromRow($row));
    }

    public function save(PlanOffering $planOffering): void
    {
        $persistedVersion = $this->connection->transaction(function () use ($planOffering): ?int {
            $currentRow = $this->connection->table(self::CURRENT_TABLE)
                ->where('id', $planOffering->id->value)
                ->lockForUpdate()
                ->first();

            $record = $this->mapper->planOfferingRecord($planOffering);

            if ($currentRow === null) {
                if ($planOffering->version() !== 0) {
                    throw new StaleCommercialCatalogueWriteException(
                        'Plan Offering already exists with a different version.',
                    );
                }

                $this->insertCurrentAndHistory($record, 1);

                return 1;
            }

            $current = $this->planOfferingRecordFromRow($currentRow);

            if ($current->version !== $planOffering->version()) {
                throw new StaleCommercialCatalogueWriteException(
                    'Plan Offering write rejected because its version is stale.',
                );
            }

            if ($this->samePlanOffering($current, $record)) {
                return null;
            }

            if ($current->planId !== $record->planId || $current->billingOptionId !== $record->billingOptionId) {
                throw new InvalidCommercialCatalogueStorageStateException(
                    'Plan Offering identity references cannot be rewritten.',
                );
            }

            $newVersion = $current->version + 1;
            $this->updateCurrent($record, $newVersion);
            $this->insertHistory($record, $newVersion);

            return $newVersion;
        });

        if ($persistedVersion !== null) {
            $planOffering->synchronizeVersion($persistedVersion);
        }
    }

    public function findAvailableForDate(string $effectiveDate): Traversable
    {
        $date = $this->calendarDate($effectiveDate);

        $rows = $this->connection->table(self::CURRENT_TABLE)
            ->whereDate('effective_start', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_end')
                    ->orWhereDate('effective_end', '>=', $date);
            })
            ->whereIn('status', ['active', 'grandfathered'])
            ->orderBy('plan_id')
            ->orderBy('display_order')
            ->orderBy('version')
            ->get();

        foreach ($rows as $row) {
            yield $this->mapper->toPlanOfferingDomain($this->planOfferingRecordFromRow($row));
        }
    }

    public function findByPlan(PlanId $planId): Traversable
    {
        $rows = $this->connection->table(self::HISTORY_TABLE)
            ->where('plan_id', $planId->value)
            ->orderBy('version')
            ->get();

        foreach ($rows as $row) {
            yield $this->mapper->toPlanOfferingDomain($this->planOfferingRecordFromRow($row));
        }
    }

    private function insertCurrentAndHistory(PlanOfferingStorageRecord $record, int $version): void
    {
        $this->insertCurrent($record, $version);
        $this->insertHistory($record, $version);
    }

    private function insertCurrent(PlanOfferingStorageRecord $record, int $version): void
    {
        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $this->connection->table(self::CURRENT_TABLE)->insert([
            'id' => $record->id,
            'plan_id' => $record->planId,
            'billing_option_id' => $record->billingOptionId,
            'amount_minor' => $record->amountMinor,
            'currency_code' => $record->currencyCode,
            'status' => $record->status,
            'effective_start' => $this->databaseDate($record->effectiveStart),
            'effective_end' => $record->effectiveEnd === null ? null : $this->databaseDate($record->effectiveEnd),
            'configuration_version' => $record->configurationVersion,
            'capability_configuration_reference' => $record->capabilityConfigurationReference,
            'display_order' => $record->displayOrder,
            'version' => $version,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function updateCurrent(PlanOfferingStorageRecord $record, int $version): void
    {
        $affected = $this->connection->table(self::CURRENT_TABLE)
            ->where('id', $record->id)
            ->where('version', $record->version)
            ->update([
                'plan_id' => $record->planId,
                'billing_option_id' => $record->billingOptionId,
                'amount_minor' => $record->amountMinor,
                'currency_code' => $record->currencyCode,
                'status' => $record->status,
                'effective_start' => $this->databaseDate($record->effectiveStart),
                'effective_end' => $record->effectiveEnd === null ? null : $this->databaseDate($record->effectiveEnd),
                'configuration_version' => $record->configurationVersion,
                'capability_configuration_reference' => $record->capabilityConfigurationReference,
                'display_order' => $record->displayOrder,
                'version' => $version,
                'updated_at' => $this->databaseTimestamp(new DateTimeImmutable),
            ]);

        if ($affected !== 1) {
            throw new StaleCommercialCatalogueWriteException(
                'Plan Offering write rejected because its version is stale.',
            );
        }
    }

    private function insertHistory(PlanOfferingStorageRecord $record, int $version): void
    {
        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $this->connection->table(self::HISTORY_TABLE)->insert([
            'id' => $record->id,
            'version' => $version,
            'plan_id' => $record->planId,
            'billing_option_id' => $record->billingOptionId,
            'amount_minor' => $record->amountMinor,
            'currency_code' => $record->currencyCode,
            'status' => $record->status,
            'effective_start' => $this->databaseDate($record->effectiveStart),
            'effective_end' => $record->effectiveEnd === null ? null : $this->databaseDate($record->effectiveEnd),
            'configuration_version' => $record->configurationVersion,
            'capability_configuration_reference' => $record->capabilityConfigurationReference,
            'display_order' => $record->displayOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function samePlanOffering(PlanOfferingStorageRecord $current, PlanOfferingStorageRecord $record): bool
    {
        return $current->planId === $record->planId
            && $current->billingOptionId === $record->billingOptionId
            && $current->amountMinor === $record->amountMinor
            && $current->currencyCode === $record->currencyCode
            && $current->status === $record->status
            && $current->configurationVersion === $record->configurationVersion
            && $current->capabilityConfigurationReference === $record->capabilityConfigurationReference
            && $current->displayOrder === $record->displayOrder
            && $this->dateTimesEqual($current->effectiveStart, $record->effectiveStart)
            && $this->dateTimesEqual($current->effectiveEnd, $record->effectiveEnd);
    }

    private function planOfferingRecordFromRow(stdClass $row): PlanOfferingStorageRecord
    {
        return new PlanOfferingStorageRecord(
            $this->stringValue($row, 'id'),
            $this->stringValue($row, 'plan_id'),
            $this->stringValue($row, 'billing_option_id'),
            $this->integerValue($row, 'amount_minor'),
            $this->stringValue($row, 'currency_code'),
            $this->stringValue($row, 'status'),
            $this->dateValue($row->effective_start ?? null, 'effective_start'),
            $this->nullableDateValue($row->effective_end ?? null, 'effective_end'),
            $this->stringValue($row, 'configuration_version'),
            $this->stringValue($row, 'capability_configuration_reference'),
            $this->integerValue($row, 'display_order'),
            $this->integerValue($row, 'version'),
        );
    }

    private function stringValue(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value)) {
            throw new InvalidCommercialCatalogueStorageStateException(sprintf('Plan Offering storage field %s must be a string.', $field));
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

        throw new InvalidCommercialCatalogueStorageStateException(sprintf('Plan Offering storage field %s must be an integer.', $field));
    }

    private function dateValue(mixed $value, string $field): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        throw new InvalidCommercialCatalogueStorageStateException(sprintf('Plan Offering storage field %s must be a date.', $field));
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

    private function calendarDate(string $date): string
    {
        $calendarDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();

        if ($calendarDate === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $calendarDate->format('Y-m-d') !== $date) {
            throw new InvalidCommercialCatalogueStorageStateException(
                'Plan Offering effective date must use canonical YYYY-MM-DD format.',
            );
        }

        return $date;
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
