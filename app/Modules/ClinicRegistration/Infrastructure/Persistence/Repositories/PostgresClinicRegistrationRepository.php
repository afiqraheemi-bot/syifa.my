<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Infrastructure\Persistence\Repositories;

use App\Modules\ClinicRegistration\Contracts\Repositories\ClinicRegistrationRepositoryInterface;
use App\Modules\ClinicRegistration\Domain\ClinicRegistration;
use App\Modules\ClinicRegistration\Domain\Exceptions\StaleClinicRegistrationWriteException;
use App\Modules\ClinicRegistration\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationId;
use App\Modules\ClinicRegistration\Infrastructure\Persistence\Exceptions\InvalidClinicRegistrationStorageStateException;
use App\Modules\ClinicRegistration\Infrastructure\Persistence\Mappers\ClinicRegistrationPersistenceMapper;
use App\Modules\ClinicRegistration\Infrastructure\Persistence\Records\ClinicRegistrationStorageRecord;
use App\Modules\ClinicRegistration\Infrastructure\Persistence\Records\DeclarationAcceptanceStorageRecord;
use App\Modules\ClinicRegistration\Infrastructure\Persistence\Records\RegistrationDecisionStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final class PostgresClinicRegistrationRepository implements ClinicRegistrationRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly ClinicRegistrationPersistenceMapper $mapper,
    ) {}

    public function find(RegistrationId $registrationId): ?ClinicRegistration
    {
        $row = $this->connection->table('clinic_registrations')
            ->where('id', $registrationId->value)
            ->first();

        return $row === null ? null : $this->registrationFromRow($row);
    }

    public function findCurrentForPlatformIdentity(PlatformIdentityReference $platformIdentity): ?ClinicRegistration
    {
        $row = $this->connection->table('clinic_registrations')
            ->where('platform_identity_id', $platformIdentity->value)
            ->orderByDesc('created_at')
            ->first();

        return $row === null ? null : $this->registrationFromRow($row);
    }

    public function findByCorrelationReference(string $correlationReference): ?ClinicRegistration
    {
        $row = $this->connection->table('clinic_registrations')
            ->where('registration_correlation_reference', $correlationReference)
            ->first();

        return $row === null ? null : $this->registrationFromRow($row);
    }

    public function save(ClinicRegistration $registration): void
    {
        $newVersion = $this->connection->transaction(
            fn (): int => $registration->version() === 0
                ? $this->insert($registration)
                : $this->update($registration),
        );

        $registration->synchronizeVersion($newVersion);
    }

    private function insert(ClinicRegistration $registration): int
    {
        $record = $this->mapper->registrationRecord($registration);

        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $this->connection->table('clinic_registrations')->insert([
            ...$this->storagePayload($record, 1),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->replaceDeclarations($registration);
        $this->persistDecisions($registration);

        return 1;
    }

    private function update(ClinicRegistration $registration): int
    {
        $record = $this->mapper->registrationRecord($registration);
        $newVersion = $record->version + 1;
        $affected = $this->connection->table('clinic_registrations')
            ->where('id', $record->id)
            ->where('version', $record->version)
            ->update([
                ...$this->storagePayload($record, $newVersion),
                'updated_at' => $this->databaseTimestamp(new DateTimeImmutable),
            ]);

        if ($affected !== 1) {
            throw new StaleClinicRegistrationWriteException('Clinic registration write rejected because its version is stale.');
        }

        $this->replaceDeclarations($registration);
        $this->persistDecisions($registration);

        return $newVersion;
    }

    /** @return array<string, mixed> */
    private function storagePayload(ClinicRegistrationStorageRecord $record, int $version): array
    {
        return [
            'id' => $record->id,
            'platform_identity_id' => $record->platformIdentityId,
            'status' => $record->status,
            'clinic_name' => $record->clinicName,
            'clinic_email' => $record->clinicEmail,
            'clinic_phone' => $record->clinicPhone,
            'clinic_address' => $record->clinicAddress,
            'selected_plan_offering_reference' => $record->selectedPlanOfferingReference,
            'selected_billing_option_reference' => $record->selectedBillingOptionReference,
            'commercial_snapshot_version' => $record->commercialSnapshotVersion,
            'registration_correlation_reference' => $record->registrationCorrelationReference,
            'reserved_tenant_id' => $record->reservedTenantId,
            'provisioned_tenant_reference' => $record->provisionedTenantReference,
            'submitted_at' => $this->nullableDatabaseTimestamp($record->submittedAt),
            'provisioned_at' => $this->nullableDatabaseTimestamp($record->provisionedAt),
            'cancelled_at' => $this->nullableDatabaseTimestamp($record->cancelledAt),
            'expired_at' => $this->nullableDatabaseTimestamp($record->expiredAt),
            'version' => $version,
        ];
    }

    private function replaceDeclarations(ClinicRegistration $registration): void
    {
        $this->connection->table('clinic_registration_declaration_acceptances')
            ->where('clinic_registration_id', $registration->id->value)
            ->delete();

        foreach ($this->mapper->declarationRecords($registration) as $record) {
            $this->connection->table('clinic_registration_declaration_acceptances')->insert([
                'id' => self::deterministicId($record),
                'clinic_registration_id' => $record->registrationId,
                'declaration_key' => $record->declarationKey,
                'declaration_version' => $record->declarationVersion,
                'accepted_at' => $this->databaseTimestamp($record->acceptedAt),
                'created_at' => $this->databaseTimestamp(new DateTimeImmutable),
                'updated_at' => $this->databaseTimestamp(new DateTimeImmutable),
            ]);
        }
    }

    private function registrationFromRow(stdClass $row): ClinicRegistration
    {
        $record = new ClinicRegistrationStorageRecord(
            $this->stringValue($row, 'id'),
            $this->stringValue($row, 'platform_identity_id'),
            $this->stringValue($row, 'status'),
            $this->nullableStringValue($row, 'clinic_name'),
            $this->nullableStringValue($row, 'clinic_email'),
            $this->nullableStringValue($row, 'clinic_phone'),
            $this->nullableStringValue($row, 'clinic_address'),
            $this->nullableStringValue($row, 'selected_plan_offering_reference'),
            $this->nullableStringValue($row, 'selected_billing_option_reference'),
            $this->nullableStringValue($row, 'commercial_snapshot_version'),
            $this->stringValue($row, 'registration_correlation_reference'),
            $this->nullableStringValue($row, 'reserved_tenant_id'),
            $this->nullableStringValue($row, 'provisioned_tenant_reference'),
            $this->nullableDateTimeValue($row->submitted_at ?? null),
            $this->nullableDateTimeValue($row->provisioned_at ?? null),
            $this->nullableDateTimeValue($row->cancelled_at ?? null),
            $this->nullableDateTimeValue($row->expired_at ?? null),
            $this->integerValue($row, 'version'),
        );

        $declarationRows = $this->connection->table('clinic_registration_declaration_acceptances')
            ->where('clinic_registration_id', $record->id)
            ->orderBy('declaration_key')
            ->get();

        $declarations = [];
        foreach ($declarationRows as $declarationRow) {
            $declarations[] = new DeclarationAcceptanceStorageRecord(
                $record->id,
                $this->stringValue($declarationRow, 'declaration_key'),
                $this->stringValue($declarationRow, 'declaration_version'),
                $this->dateTimeValue($declarationRow->accepted_at ?? null, 'accepted_at'),
            );
        }

        $decisionRows = $this->connection->table('clinic_registration_decisions')
            ->where('clinic_registration_id', $record->id)
            ->orderBy('decided_at')
            ->orderBy('id')
            ->get();
        $decisions = [];
        foreach ($decisionRows as $decisionRow) {
            $decisions[] = new RegistrationDecisionStorageRecord(
                $this->stringValue($decisionRow, 'id'),
                $record->id,
                $this->stringValue($decisionRow, 'outcome'),
                $this->stringValue($decisionRow, 'reason_category'),
                $this->nullableStringValue($decisionRow, 'correction_instructions'),
                $this->stringValue($decisionRow, 'decided_by_platform_identity_id'),
                $this->dateTimeValue($decisionRow->decided_at ?? null, 'decided_at'),
                $this->nullableDateTimeValue($decisionRow->superseded_at ?? null),
            );
        }

        return $this->mapper->toDomain($record, $declarations, $decisions);
    }

    private function persistDecisions(ClinicRegistration $registration): void
    {
        foreach ($this->mapper->decisionRecords($registration) as $record) {
            $existing = $this->connection->table('clinic_registration_decisions')
                ->where('id', $record->id)
                ->first();

            if ($existing === null) {
                $now = $this->databaseTimestamp(new DateTimeImmutable);
                $this->connection->table('clinic_registration_decisions')->insert([
                    'id' => $record->id,
                    'clinic_registration_id' => $record->registrationId,
                    'outcome' => $record->outcome,
                    'reason_category' => $record->reasonCategory,
                    'correction_instructions' => $record->correctionInstructions,
                    'decided_by_platform_identity_id' => $record->decidedByPlatformIdentityId,
                    'decided_at' => $this->databaseTimestamp($record->decidedAt),
                    'superseded_at' => $this->nullableDatabaseTimestamp($record->supersededAt),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            if ($record->supersededAt !== null && ($existing->superseded_at ?? null) === null) {
                $this->connection->table('clinic_registration_decisions')
                    ->where('id', $record->id)
                    ->whereNull('superseded_at')
                    ->update([
                        'superseded_at' => $this->databaseTimestamp($record->supersededAt),
                        'updated_at' => $this->databaseTimestamp(new DateTimeImmutable),
                    ]);
            }
        }
    }

    private function stringValue(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value)) {
            throw new InvalidClinicRegistrationStorageStateException(sprintf('Storage field %s must be a string.', $field));
        }

        return $value;
    }

    private function nullableStringValue(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        if ($value === null || is_string($value)) {
            return $value;
        }

        throw new InvalidClinicRegistrationStorageStateException(sprintf('Storage field %s must be a string or null.', $field));
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

        throw new InvalidClinicRegistrationStorageStateException(sprintf('Storage field %s must be an integer.', $field));
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

        throw new InvalidClinicRegistrationStorageStateException(sprintf('Storage field %s must be a timestamp.', $field));
    }

    private function nullableDatabaseTimestamp(?DateTimeInterface $dateTime): ?string
    {
        return $dateTime === null ? null : $this->databaseTimestamp($dateTime);
    }

    private function databaseTimestamp(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s.uP');
    }

    private static function deterministicId(DeclarationAcceptanceStorageRecord $record): string
    {
        $hex = substr(hash('sha256', implode('|', [$record->registrationId, $record->declarationKey, $record->declarationVersion])), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
