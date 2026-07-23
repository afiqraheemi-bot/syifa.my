<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\EmailVerification;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditCorrelationIdResolverInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityLookupInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialData;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialLookupInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialStateWriterInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Verification is deliberately identity-driven, not session-driven — a
 * visitor clicking the emailed, signed link need not currently be
 * authenticated (matching Laravel's own native `verification.verify`
 * convention). The signature itself (`hash`, `sha1` of the email) is the
 * proof of possession; the `signed` route middleware already rejects a
 * tampered or expired URL before this service ever runs.
 */
final readonly class VerifyPlatformEmailService
{
    public function __construct(
        private PlatformIdentityLookupInterface $identities,
        private PlatformWorkforceCredentialLookupInterface $credentialLookup,
        private PlatformWorkforceCredentialStateWriterInterface $credentialWriter,
        private AuditEntryRecorderInterface $auditEntries,
        private AuditCorrelationIdResolverInterface $correlationIds,
        private LoggerInterface $logger,
    ) {}

    public function execute(string $platformIdentityId, string $hash, DateTimeImmutable $verifiedAt): bool
    {
        $correlationId = $this->correlationIds->resolve();
        $identity = $this->identities->findById($platformIdentityId);

        if ($identity === null) {
            $this->audit($correlationId, $verifiedAt, $platformIdentityId, false, 'identity_not_found');

            return false;
        }

        if (! hash_equals(sha1($identity->email), $hash)) {
            $this->audit($correlationId, $verifiedAt, $platformIdentityId, false, 'hash_mismatch');

            return false;
        }

        $credential = $this->credentialLookup->findByNormalizedEmail($identity->email);

        if ($credential === null) {
            $this->audit($correlationId, $verifiedAt, $platformIdentityId, false, 'credential_not_found');

            return false;
        }

        if ($credential->emailVerified) {
            $this->audit($correlationId, $verifiedAt, $platformIdentityId, true, null);

            return true;
        }

        try {
            $this->credentialWriter->saveState(
                new PlatformWorkforceCredentialData(
                    $credential->platformIdentityId,
                    $credential->normalizedEmail,
                    true,
                    $verifiedAt,
                    $credential->accountStatus,
                    $credential->failedAttemptCount,
                    $credential->lockoutUntil,
                    $credential->version,
                    $credential->createdAt,
                    $credential->updatedAt,
                ),
                $verifiedAt,
            );
        } catch (Throwable) {
            $this->audit($correlationId, $verifiedAt, $platformIdentityId, false, 'write_failed');

            return false;
        }

        $this->audit($correlationId, $verifiedAt, $platformIdentityId, true, null);

        return true;
    }

    private function audit(
        string $correlationId,
        DateTimeImmutable $occurredAt,
        string $platformIdentityId,
        bool $succeeded,
        ?string $reasonCode,
    ): void {
        try {
            $this->auditEntries->record(new AuditEntryData(
                self::auditEntryId(),
                $occurredAt,
                new AuditActorData(AuditActorType::PlatformIdentity->value, $platformIdentityId),
                null,
                'platform.authentication.email_verified',
                new AuditTargetData('platform_session', $platformIdentityId),
                new AuditOutcomeData(
                    $succeeded ? AuditOutcomeType::Succeeded->value : AuditOutcomeType::Failed->value,
                    $reasonCode,
                ),
                $correlationId,
                [],
            ));
        } catch (Throwable) {
            $this->logger->critical('platform.security.audit.emergency', [
                'correlation_id' => $correlationId,
                'actor_type' => AuditActorType::PlatformIdentity->value,
                'actor_identity_id' => $platformIdentityId,
                'action' => 'platform.authentication.email_verified',
                'outcome' => $succeeded ? AuditOutcomeType::Succeeded->value : AuditOutcomeType::Failed->value,
                'reason_code' => $reasonCode,
                'timestamp' => $occurredAt->format('Y-m-d\TH:i:s\Z'),
            ]);
        }
    }

    private static function auditEntryId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
