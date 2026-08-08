<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Infrastructure\Persistence\Repositories;

use App\Modules\AcquisitionOffer\Contracts\Repositories\CommercialOfferRepositoryInterface;
use App\Modules\AcquisitionOffer\Domain\CommercialOffer;
use App\Modules\AcquisitionOffer\Domain\Exceptions\StaleCommercialOfferWriteException;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\ClinicRegistrationReference;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\CommercialOfferId;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\AcquisitionOffer\Infrastructure\Persistence\Exceptions\InvalidCommercialOfferStorageStateException;
use App\Modules\AcquisitionOffer\Infrastructure\Persistence\Mappers\CommercialOfferPersistenceMapper;
use App\Modules\AcquisitionOffer\Infrastructure\Persistence\Records\CommercialOfferLineItemStorageRecord;
use App\Modules\AcquisitionOffer\Infrastructure\Persistence\Records\CommercialOfferStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final readonly class PostgresCommercialOfferRepository implements CommercialOfferRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private CommercialOfferPersistenceMapper $mapper,
    ) {}

    public function find(CommercialOfferId $commercialOfferId): ?CommercialOffer
    {
        $row = $this->connection->table('commercial_offers')
            ->where('id', $commercialOfferId->value)
            ->first();

        return $row === null ? null : $this->offerFromRow($row);
    }

    public function findCurrentForPlatformIdentity(PlatformIdentityReference $platformIdentity): ?CommercialOffer
    {
        $row = $this->connection->table('commercial_offers')
            ->where('platform_identity_id', $platformIdentity->value)
            ->where('status', 'prepared')
            ->orderByDesc('created_at')
            ->first();

        return $row === null ? null : $this->offerFromRow($row);
    }

    public function findCurrentForClinicRegistration(ClinicRegistrationReference $clinicRegistration): ?CommercialOffer
    {
        $row = $this->connection->table('commercial_offers')
            ->where('clinic_registration_id', $clinicRegistration->value)
            ->where('owner_kind', 'clinic_registration')
            ->where('purpose', 'initial_checkout')
            ->whereIn('status', ['prepared', 'claimed'])
            ->orderByDesc('created_at')
            ->first();

        return $row === null ? null : $this->offerFromRow($row);
    }

    public function findInitialAcquisitionForRegistration(
        CommercialOfferId $commercialOfferId,
        ClinicRegistrationReference $clinicRegistration,
    ): ?CommercialOffer {
        $row = $this->connection->table('commercial_offers')
            ->where('id', $commercialOfferId->value)
            ->where('clinic_registration_id', $clinicRegistration->value)
            ->where('owner_kind', 'clinic_registration')
            ->where('purpose', 'initial_checkout')
            ->whereIn('status', ['prepared', 'claimed'])
            ->first();

        return $row === null ? null : $this->offerFromRow($row);
    }

    public function save(CommercialOffer $commercialOffer): void
    {
        $newVersion = $this->connection->transaction(
            fn (): int => $commercialOffer->version() === 0
                ? $this->insert($commercialOffer)
                : $this->update($commercialOffer),
        );

        $commercialOffer->synchronizeVersion($newVersion);
    }

    private function insert(CommercialOffer $offer): int
    {
        $record = $this->mapper->offerRecord($offer);
        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $this->connection->table('commercial_offers')->insert([
            ...$this->payload($record, 1),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->replaceLineItems($offer);

        return 1;
    }

    private function update(CommercialOffer $offer): int
    {
        $record = $this->mapper->offerRecord($offer);
        $newVersion = $record->version + 1;
        $affected = $this->connection->table('commercial_offers')
            ->where('id', $record->id)
            ->where('version', $record->version)
            ->update([
                ...$this->payload($record, $newVersion),
                'updated_at' => $this->databaseTimestamp(new DateTimeImmutable),
            ]);

        if ($affected !== 1) {
            throw new StaleCommercialOfferWriteException('Commercial Offer write rejected because its version is stale.');
        }

        $this->replaceLineItems($offer);

        return $newVersion;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CommercialOfferStorageRecord $record, int $version): array
    {
        return [
            'id' => $record->id,
            'platform_identity_id' => $record->platformIdentityId,
            'owner_kind' => $record->platformIdentityId === null ? 'clinic_registration' : 'platform_identity',
            'purpose' => 'initial_checkout',
            'clinic_registration_id' => $record->clinicRegistrationId,
            'tenant_id' => $record->tenantId,
            'status' => $record->status,
            'plan_offering_id' => $record->planOfferingId,
            'plan_id' => $record->planId,
            'billing_cycle_id' => $record->billingCycleId,
            'billing_period_start' => $record->billingPeriodStart,
            'billing_period_end' => $record->billingPeriodEnd,
            'offering_configuration_version' => $record->offeringConfigurationVersion,
            'capability_configuration_reference' => $record->capabilityConfigurationReference,
            'subtotal_amount_minor' => $record->subtotalAmountMinor,
            'total_amount_minor' => $record->totalAmountMinor,
            'currency' => $record->currency,
            'expires_at' => $this->databaseTimestamp($record->expiresAt),
            'claimed_payment_id' => $record->claimedPaymentId,
            'claimed_at' => $this->nullableDatabaseTimestamp($record->claimedAt),
            'cancelled_at' => $this->nullableDatabaseTimestamp($record->cancelledAt),
            'expired_at' => $this->nullableDatabaseTimestamp($record->expiredAt),
            'correlation_id' => $record->correlationId,
            'version' => $version,
        ];
    }

    private function replaceLineItems(CommercialOffer $offer): void
    {
        $this->connection->table('commercial_offer_line_items')
            ->where('commercial_offer_id', $offer->id->value)
            ->delete();

        foreach ($this->mapper->lineItemRecords($offer) as $record) {
            $this->connection->table('commercial_offer_line_items')->insert([
                'id' => self::deterministicLineItemId($record),
                'commercial_offer_id' => $record->commercialOfferId,
                'item_type' => $record->itemType,
                'item_reference' => $record->itemReference,
                'description' => $record->description,
                'quantity' => $record->quantity,
                'unit_amount_minor' => $record->unitAmountMinor,
                'total_amount_minor' => $record->totalAmountMinor,
                'currency' => $record->currency,
                'catalogue_snapshot_reference' => $record->catalogueSnapshotReference,
                'position' => $record->position,
                'created_at' => $this->databaseTimestamp(new DateTimeImmutable),
                'updated_at' => $this->databaseTimestamp(new DateTimeImmutable),
            ]);
        }
    }

    private function offerFromRow(stdClass $row): CommercialOffer
    {
        $record = new CommercialOfferStorageRecord(
            $this->stringValue($row, 'id'),
            $this->nullableStringValue($row->platform_identity_id ?? null, 'platform_identity_id'),
            $this->stringValue($row, 'clinic_registration_id'),
            $this->nullableStringValue($row->tenant_id ?? null, 'tenant_id'),
            $this->stringValue($row, 'status'),
            $this->stringValue($row, 'plan_offering_id'),
            $this->stringValue($row, 'plan_id'),
            $this->stringValue($row, 'billing_cycle_id'),
            $this->stringValue($row, 'billing_period_start'),
            $this->stringValue($row, 'billing_period_end'),
            $this->stringValue($row, 'offering_configuration_version'),
            $this->stringValue($row, 'capability_configuration_reference'),
            $this->integerValue($row, 'subtotal_amount_minor'),
            $this->integerValue($row, 'total_amount_minor'),
            $this->stringValue($row, 'currency'),
            $this->dateTimeValue($row->expires_at ?? null, 'expires_at'),
            $this->nullableStringValue($row->claimed_payment_id ?? null, 'claimed_payment_id'),
            $this->nullableDateTimeValue($row->claimed_at ?? null),
            $this->nullableDateTimeValue($row->cancelled_at ?? null),
            $this->nullableDateTimeValue($row->expired_at ?? null),
            $this->stringValue($row, 'correlation_id'),
            $this->integerValue($row, 'version'),
        );

        $lineItemRows = $this->connection->table('commercial_offer_line_items')
            ->where('commercial_offer_id', $record->id)
            ->orderBy('position')
            ->get();

        $lineItems = [];
        foreach ($lineItemRows as $lineItemRow) {
            $lineItems[] = new CommercialOfferLineItemStorageRecord(
                $record->id,
                $this->stringValue($lineItemRow, 'item_type'),
                $this->stringValue($lineItemRow, 'item_reference'),
                $this->stringValue($lineItemRow, 'description'),
                $this->integerValue($lineItemRow, 'quantity'),
                $this->integerValue($lineItemRow, 'unit_amount_minor'),
                $this->integerValue($lineItemRow, 'total_amount_minor'),
                $this->stringValue($lineItemRow, 'currency'),
                $this->stringValue($lineItemRow, 'catalogue_snapshot_reference'),
                $this->integerValue($lineItemRow, 'position'),
            );
        }

        return $this->mapper->toDomain($record, $lineItems);
    }

    private function stringValue(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value)) {
            throw new InvalidCommercialOfferStorageStateException(sprintf('Storage field %s must be a string.', $field));
        }

        return $value;
    }

    private function nullableStringValue(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidCommercialOfferStorageStateException(sprintf('Storage field %s must be a string.', $field));
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

        throw new InvalidCommercialOfferStorageStateException(sprintf('Storage field %s must be an integer.', $field));
    }

    private function nullableDateTimeValue(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : $this->dateTimeValue($value, 'timestamp');
    }

    private function dateTimeValue(mixed $value, string $field): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        throw new InvalidCommercialOfferStorageStateException(sprintf('Storage field %s must be a timestamp.', $field));
    }

    private function nullableDatabaseTimestamp(?DateTimeInterface $dateTime): ?string
    {
        return $dateTime === null ? null : $this->databaseTimestamp($dateTime);
    }

    private function databaseTimestamp(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s.uP');
    }

    private static function deterministicLineItemId(CommercialOfferLineItemStorageRecord $record): string
    {
        $hex = substr(hash('sha256', implode('|', [$record->commercialOfferId, (string) $record->position, $record->itemReference])), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
