<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Infrastructure\Persistence\Queries;

use App\Modules\ClinicRegistration\Contracts\Data\ClinicRegistrationData;
use App\Modules\ClinicRegistration\Contracts\Data\DeclarationAcceptanceData;
use App\Modules\ClinicRegistration\Contracts\Data\RegistrationDecisionData;
use App\Modules\ClinicRegistration\Contracts\Queries\ClinicRegistrationQueryInterface;
use App\Modules\ClinicRegistration\Infrastructure\Persistence\Exceptions\InvalidClinicRegistrationStorageStateException;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final readonly class PostgresClinicRegistrationQueryAdapter implements ClinicRegistrationQueryInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function currentForPlatformIdentity(string $platformIdentityId): ?ClinicRegistrationData
    {
        $row = $this->connection->table('clinic_registrations')
            ->where('platform_identity_id', $platformIdentityId)
            ->orderByDesc('created_at')
            ->first();

        return $row === null ? null : $this->dataFromRow($row);
    }

    public function currentForTrackingCredential(string $trackingCredential): ?ClinicRegistrationData
    {
        $row = $this->connection->table('clinic_registrations')
            ->where('platform_identity_id', $trackingCredential)
            ->orderByDesc('created_at')
            ->first();

        return $row === null ? null : $this->dataFromRow($row);
    }

    private function dataFromRow(stdClass $row): ClinicRegistrationData
    {
        $registrationId = $this->stringValue($row, 'id');
        $declarationRows = $this->connection->table('clinic_registration_declaration_acceptances')
            ->where('clinic_registration_id', $registrationId)
            ->orderBy('declaration_key')
            ->get();

        $declarations = [];
        foreach ($declarationRows as $declarationRow) {
            $declarations[] = new DeclarationAcceptanceData(
                $this->stringValue($declarationRow, 'declaration_key'),
                $this->stringValue($declarationRow, 'declaration_version'),
                $this->dateTimeValue($declarationRow->accepted_at ?? null, 'accepted_at')->format(DateTimeInterface::ATOM),
            );
        }

        $decisionRows = $this->connection->table('clinic_registration_decisions')
            ->where('clinic_registration_id', $registrationId)
            ->orderBy('decided_at')
            ->orderBy('id')
            ->get();
        $decisions = [];
        foreach ($decisionRows as $decisionRow) {
            $decisions[] = new RegistrationDecisionData(
                $this->stringValue($decisionRow, 'id'),
                $this->stringValue($decisionRow, 'outcome'),
                $this->stringValue($decisionRow, 'reason_category'),
                $this->nullableStringValue($decisionRow, 'correction_instructions'),
                $this->stringValue($decisionRow, 'decided_by_platform_identity_id'),
                $this->dateTimeValue($decisionRow->decided_at ?? null, 'decided_at')->format(DateTimeInterface::ATOM),
                $this->instant($this->nullableDateTimeValue($decisionRow->superseded_at ?? null)),
            );
        }

        return new ClinicRegistrationData(
            $registrationId,
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
            $this->instant($this->nullableDateTimeValue($row->submitted_at ?? null)),
            $this->instant($this->nullableDateTimeValue($row->provisioned_at ?? null)),
            $this->instant($this->nullableDateTimeValue($row->cancelled_at ?? null)),
            $this->instant($this->nullableDateTimeValue($row->expired_at ?? null)),
            $this->integerValue($row, 'version'),
            $declarations,
            $decisions,
            $this->nullableStringValue($row, 'preferred_subdomain'),
            $this->nullableStringValue($row, 'selected_website_template'),
        );
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

    private function instant(?DateTimeInterface $dateTime): ?string
    {
        return $dateTime?->format(DateTimeInterface::ATOM);
    }
}
