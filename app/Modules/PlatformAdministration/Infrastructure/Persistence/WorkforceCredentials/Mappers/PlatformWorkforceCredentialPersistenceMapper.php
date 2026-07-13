<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\Mappers;

use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialData;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\Exceptions\InvalidPlatformWorkforceCredentialStorageStateException;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\Records\PlatformWorkforceCredentialStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use stdClass;

final class PlatformWorkforceCredentialPersistenceMapper
{
    public function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function toData(PlatformWorkforceCredentialStorageRecord $record): PlatformWorkforceCredentialData
    {
        return new PlatformWorkforceCredentialData(
            $record->platformIdentityId,
            $record->normalizedEmail,
            $record->emailVerificationStatus === 'verified',
            $record->emailVerifiedAt,
            $record->accountStatus,
            $record->failedAttemptCount,
            $record->lockoutUntil,
            $record->version,
            $record->createdAt,
            $record->updatedAt,
        );
    }

    public function fromRow(stdClass $row): PlatformWorkforceCredentialStorageRecord
    {
        return new PlatformWorkforceCredentialStorageRecord(
            $this->stringValue($row, 'platform_identity_id'),
            $this->stringValue($row, 'normalized_email'),
            $this->stringValue($row, 'password_hash'),
            $this->stringValue($row, 'email_verification_status'),
            $this->nullableDateTimeValue($row->email_verified_at ?? null, 'email_verified_at'),
            $this->stringValue($row, 'account_status'),
            $this->integerValue($row, 'failed_attempt_count'),
            $this->nullableDateTimeValue($row->lockout_until ?? null, 'lockout_until'),
            $this->integerValue($row, 'version'),
            $this->dateTimeValue($row->created_at ?? null, 'created_at'),
            $this->dateTimeValue($row->updated_at ?? null, 'updated_at'),
        );
    }

    private function stringValue(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value)) {
            throw new InvalidPlatformWorkforceCredentialStorageStateException($field.' must be a string.');
        }

        return $value;
    }

    private function integerValue(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new InvalidPlatformWorkforceCredentialStorageStateException($field.' must be an integer.');
        }

        return (int) $value;
    }

    private function dateTimeValue(mixed $value, string $field): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (! is_string($value)) {
            throw new InvalidPlatformWorkforceCredentialStorageStateException($field.' must be an instant.');
        }

        return new DateTimeImmutable($value);
    }

    private function nullableDateTimeValue(mixed $value, string $field): ?DateTimeImmutable
    {
        return $value === null ? null : $this->dateTimeValue($value, $field);
    }
}
