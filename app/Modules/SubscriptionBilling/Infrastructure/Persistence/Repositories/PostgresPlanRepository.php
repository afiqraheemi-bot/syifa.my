<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories;

use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Plan;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanCode;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\InvalidCommercialCatalogueStorageStateException;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\StaleCommercialCatalogueWriteException;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\CommercialCataloguePersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\PlanStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final class PostgresPlanRepository implements PlanRepositoryInterface
{
    private const string CURRENT_TABLE = 'commercial_catalogue_plans';

    private const string HISTORY_TABLE = 'commercial_catalogue_plan_versions';

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly CommercialCataloguePersistenceMapper $mapper,
    ) {}

    public function findById(PlanId $planId): ?Plan
    {
        $row = $this->connection->table(self::CURRENT_TABLE)
            ->where('id', $planId->value)
            ->first();

        return $row === null ? null : $this->mapper->toPlanDomain($this->planRecordFromRow($row));
    }

    public function findByCode(PlanCode $planCode): ?Plan
    {
        $row = $this->connection->table(self::CURRENT_TABLE)
            ->where('code', $planCode->value)
            ->first();

        return $row === null ? null : $this->mapper->toPlanDomain($this->planRecordFromRow($row));
    }

    public function existsByCode(PlanCode $planCode): bool
    {
        return $this->connection->table(self::CURRENT_TABLE)
            ->where('code', $planCode->value)
            ->exists();
    }

    public function save(Plan $plan): void
    {
        $persistedVersion = $this->connection->transaction(function () use ($plan): ?int {
            $currentRow = $this->connection->table(self::CURRENT_TABLE)
                ->where('id', $plan->id->value)
                ->lockForUpdate()
                ->first();

            $record = $this->mapper->planRecord($plan);

            if ($currentRow === null) {
                if ($plan->version() !== 0) {
                    throw new StaleCommercialCatalogueWriteException('Plan already exists with a different version.');
                }

                $this->insertCurrentAndHistory($record, 1);

                return 1;
            }

            $current = $this->planRecordFromRow($currentRow);

            if ($current->version !== $plan->version()) {
                throw new StaleCommercialCatalogueWriteException('Plan write rejected because its version is stale.');
            }

            if ($current->code !== $record->code || ! $this->dateTimesEqual($current->createdAt, $record->createdAt)) {
                throw new InvalidCommercialCatalogueStorageStateException(
                    'Plan code and created timestamp cannot be rewritten.',
                );
            }

            if ($this->samePlan($current, $record)) {
                return null;
            }

            $newVersion = $current->version + 1;

            $this->updateCurrent($record, $newVersion);
            $this->insertHistory($record, $newVersion);

            return $newVersion;
        });

        if ($persistedVersion !== null) {
            $plan->synchronizeVersion($persistedVersion);
        }
    }

    private function insertCurrentAndHistory(PlanStorageRecord $record, int $version): void
    {
        $this->insertCurrent($record, $version);
        $this->insertHistory($record, $version);
    }

    private function insertCurrent(PlanStorageRecord $record, int $version): void
    {
        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $this->connection->table(self::CURRENT_TABLE)->insert([
            'id' => $record->id,
            'code' => $record->code,
            'name' => $record->name,
            'description' => $record->description,
            'status' => $record->status,
            'display_order' => $record->displayOrder,
            'domain_created_at' => $this->databaseTimestamp($record->createdAt),
            'domain_last_changed_at' => $this->databaseTimestamp($record->lastChangedAt),
            'version' => $version,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function updateCurrent(PlanStorageRecord $record, int $version): void
    {
        $affected = $this->connection->table(self::CURRENT_TABLE)
            ->where('id', $record->id)
            ->where('version', $record->version)
            ->update([
                'code' => $record->code,
                'name' => $record->name,
                'description' => $record->description,
                'status' => $record->status,
                'display_order' => $record->displayOrder,
                'domain_created_at' => $this->databaseTimestamp($record->createdAt),
                'domain_last_changed_at' => $this->databaseTimestamp($record->lastChangedAt),
                'version' => $version,
                'updated_at' => $this->databaseTimestamp(new DateTimeImmutable),
            ]);

        if ($affected !== 1) {
            throw new StaleCommercialCatalogueWriteException('Plan write rejected because its version is stale.');
        }
    }

    private function insertHistory(PlanStorageRecord $record, int $version): void
    {
        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $this->connection->table(self::HISTORY_TABLE)->insert([
            'id' => $record->id,
            'version' => $version,
            'code' => $record->code,
            'name' => $record->name,
            'description' => $record->description,
            'status' => $record->status,
            'display_order' => $record->displayOrder,
            'domain_created_at' => $this->databaseTimestamp($record->createdAt),
            'domain_last_changed_at' => $this->databaseTimestamp($record->lastChangedAt),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function samePlan(PlanStorageRecord $current, PlanStorageRecord $record): bool
    {
        return $current->code === $record->code
            && $current->name === $record->name
            && $current->description === $record->description
            && $current->status === $record->status
            && $current->displayOrder === $record->displayOrder
            && $this->dateTimesEqual($current->createdAt, $record->createdAt)
            && $this->dateTimesEqual($current->lastChangedAt, $record->lastChangedAt);
    }

    private function planRecordFromRow(stdClass $row): PlanStorageRecord
    {
        return new PlanStorageRecord(
            $this->stringValue($row, 'id'),
            $this->stringValue($row, 'code'),
            $this->stringValue($row, 'name'),
            $this->stringValue($row, 'description'),
            $this->stringValue($row, 'status'),
            $this->integerValue($row, 'display_order'),
            $this->dateTimeValue($row->domain_created_at ?? null, 'domain_created_at'),
            $this->dateTimeValue($row->domain_last_changed_at ?? null, 'domain_last_changed_at'),
            $this->integerValue($row, 'version'),
        );
    }

    private function stringValue(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value)) {
            throw new InvalidCommercialCatalogueStorageStateException(sprintf('Plan storage field %s must be a string.', $field));
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

        throw new InvalidCommercialCatalogueStorageStateException(sprintf('Plan storage field %s must be an integer.', $field));
    }

    private function dateTimeValue(mixed $value, string $field): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        throw new InvalidCommercialCatalogueStorageStateException(sprintf('Plan storage field %s must be a timestamp.', $field));
    }

    private function dateTimesEqual(DateTimeInterface $left, DateTimeInterface $right): bool
    {
        return $left->format('Uu') === $right->format('Uu');
    }

    private function databaseTimestamp(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s.uP');
    }
}
