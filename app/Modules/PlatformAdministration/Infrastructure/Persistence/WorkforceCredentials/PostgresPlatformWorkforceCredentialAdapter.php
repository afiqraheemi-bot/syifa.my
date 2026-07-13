<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials;

use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\CredentialVerificationInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\CredentialVerificationResult;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialData;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialLookupInterface;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\Exceptions\InvalidPlatformWorkforceCredentialStorageStateException;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\Exceptions\StalePlatformWorkforceCredentialWriteException;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\Mappers\PlatformWorkforceCredentialPersistenceMapper;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\Records\PlatformWorkforceCredentialStorageRecord;
use DateTimeImmutable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\ConnectionInterface;
use SensitiveParameter;
use stdClass;

final class PostgresPlatformWorkforceCredentialAdapter implements CredentialVerificationInterface, PlatformWorkforceCredentialLookupInterface
{
    private const int FAILED_ATTEMPT_LIMIT = 5;

    private const int LOCKOUT_MINUTES = 15;

    private readonly string $unknownIdentityHash;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly PlatformWorkforceCredentialPersistenceMapper $mapper,
        private readonly Hasher $hasher,
    ) {
        $this->unknownIdentityHash = $this->hasher->make(bin2hex(random_bytes(32)));
    }

    public function findByNormalizedEmail(string $email): ?PlatformWorkforceCredentialData
    {
        $row = $this->connection
            ->table('platform_workforce_credentials')
            ->where('normalized_email', $this->mapper->normalizeEmail($email))
            ->first();

        if (! $row instanceof stdClass) {
            return null;
        }

        return $this->mapper->toData($this->mapper->fromRow($row));
    }

    public function store(
        string $platformIdentityId,
        string $email,
        #[SensitiveParameter] string $plainPassword,
        string $accountStatus,
        bool $emailVerified,
        ?DateTimeImmutable $emailVerifiedAt,
        DateTimeImmutable $createdAt,
    ): PlatformWorkforceCredentialData {
        $normalizedEmail = $this->mapper->normalizeEmail($email);

        if (filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidPlatformWorkforceCredentialStorageStateException(
                'A platform workforce credential email must be valid.',
            );
        }

        $this->connection->table('platform_workforce_credentials')->insert([
            'platform_identity_id' => $platformIdentityId,
            'normalized_email' => $normalizedEmail,
            'password_hash' => $this->hasher->make($plainPassword),
            'email_verification_status' => $emailVerified ? 'verified' : 'unverified',
            'email_verified_at' => $this->nullableTimestamp($emailVerifiedAt),
            'account_status' => $accountStatus,
            'failed_attempt_count' => 0,
            'lockout_until' => null,
            'version' => 1,
            'created_at' => $this->timestamp($createdAt),
            'updated_at' => $this->timestamp($createdAt),
        ]);

        $credential = $this->findByNormalizedEmail($normalizedEmail);

        if ($credential === null) {
            throw new InvalidPlatformWorkforceCredentialStorageStateException(
                'Stored platform workforce credential could not be reloaded.',
            );
        }

        return $credential;
    }

    public function verify(
        string $email,
        #[SensitiveParameter] string $plainPassword,
        DateTimeImmutable $verifiedAt,
    ): CredentialVerificationResult {
        $result = $this->connection->transaction(function () use ($email, $plainPassword, $verifiedAt): CredentialVerificationResult {
            $row = $this->connection
                ->table('platform_workforce_credentials')
                ->where('normalized_email', $this->mapper->normalizeEmail($email))
                ->lockForUpdate()
                ->first();

            if (! $row instanceof stdClass) {
                $this->hasher->check($plainPassword, $this->unknownIdentityHash);

                return $this->rejected();
            }

            $record = $this->mapper->fromRow($row);
            $passwordMatches = $this->hasher->check($plainPassword, $record->passwordHash);

            if ($record->lockoutUntil !== null && $record->lockoutUntil > $verifiedAt) {
                return $this->rejected();
            }

            if (! $passwordMatches) {
                $failedAttempts = $record->lockoutUntil === null
                    ? $record->failedAttemptCount + 1
                    : 1;
                $failedAttempts = min($failedAttempts, self::FAILED_ATTEMPT_LIMIT);
                $lockoutUntil = $failedAttempts === self::FAILED_ATTEMPT_LIMIT
                    ? $verifiedAt->modify('+'.self::LOCKOUT_MINUTES.' minutes')
                    : null;

                $this->updateAttemptState($record, $failedAttempts, $lockoutUntil, $verifiedAt);

                return $this->rejected();
            }

            if ($record->failedAttemptCount !== 0 || $record->lockoutUntil !== null) {
                $this->updateAttemptState($record, 0, null, $verifiedAt);
            }

            if ($record->accountStatus !== 'active') {
                return $this->rejected();
            }

            return new CredentialVerificationResult(true, $record->platformIdentityId);
        });

        if (! $result instanceof CredentialVerificationResult) {
            throw new InvalidPlatformWorkforceCredentialStorageStateException(
                'Credential verification transaction returned an invalid result.',
            );
        }

        return $result;
    }

    public function saveState(
        PlatformWorkforceCredentialData $credential,
        DateTimeImmutable $updatedAt,
    ): PlatformWorkforceCredentialData {
        $newVersion = $credential->version + 1;
        $affected = $this->connection
            ->table('platform_workforce_credentials')
            ->where('platform_identity_id', $credential->platformIdentityId)
            ->where('version', $credential->version)
            ->update([
                'email_verification_status' => $credential->emailVerified ? 'verified' : 'unverified',
                'email_verified_at' => $this->nullableTimestamp($credential->emailVerifiedAt),
                'account_status' => $credential->accountStatus,
                'failed_attempt_count' => $credential->failedAttemptCount,
                'lockout_until' => $this->nullableTimestamp($credential->lockoutUntil),
                'version' => $newVersion,
                'updated_at' => $this->timestamp($updatedAt),
            ]);

        if ($affected !== 1) {
            throw new StalePlatformWorkforceCredentialWriteException(
                'Platform workforce credential state write rejected because its version is stale.',
            );
        }

        $reloaded = $this->findByNormalizedEmail($credential->normalizedEmail);

        if ($reloaded === null) {
            throw new InvalidPlatformWorkforceCredentialStorageStateException(
                'Updated platform workforce credential could not be reloaded.',
            );
        }

        return $reloaded;
    }

    private function updateAttemptState(
        PlatformWorkforceCredentialStorageRecord $record,
        int $failedAttemptCount,
        ?DateTimeImmutable $lockoutUntil,
        DateTimeImmutable $updatedAt,
    ): void {
        $affected = $this->connection
            ->table('platform_workforce_credentials')
            ->where('platform_identity_id', $record->platformIdentityId)
            ->where('version', $record->version)
            ->update([
                'failed_attempt_count' => $failedAttemptCount,
                'lockout_until' => $this->nullableTimestamp($lockoutUntil),
                'version' => $record->version + 1,
                'updated_at' => $this->timestamp($updatedAt),
            ]);

        if ($affected !== 1) {
            throw new StalePlatformWorkforceCredentialWriteException(
                'Platform workforce credential attempt state write rejected because its version is stale.',
            );
        }
    }

    private function rejected(): CredentialVerificationResult
    {
        return new CredentialVerificationResult(false, null);
    }

    private function timestamp(DateTimeImmutable $instant): string
    {
        return $instant->format('Y-m-d H:i:s.uP');
    }

    private function nullableTimestamp(?DateTimeImmutable $instant): ?string
    {
        return $instant?->format('Y-m-d H:i:s.uP');
    }
}
