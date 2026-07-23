<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\PasswordReset;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditCorrelationIdResolverInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialData;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialLookupInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Requesting a reset link never reveals whether the email exists — the
 * caller (Controller) must always respond with the same generic
 * acknowledgement regardless of this service's outcome; the distinction is
 * captured only in the audit trail, never in the HTTP response.
 */
final readonly class RequestPlatformPasswordResetService
{
    public function __construct(
        private PasswordBrokerFactory $passwords,
        private PlatformWorkforceCredentialLookupInterface $credentialLookup,
        private AuditEntryRecorderInterface $auditEntries,
        private AuditCorrelationIdResolverInterface $correlationIds,
        private LoggerInterface $logger,
    ) {}

    public function execute(string $email, DateTimeImmutable $requestedAt): void
    {
        $correlationId = $this->correlationIds->resolve();
        $credential = $this->credentialLookup->findByNormalizedEmail($email);

        $status = $this->passwords->broker('platform_identities')->sendResetLink(['email' => $email]);

        $this->audit($correlationId, $requestedAt, $credential, $status);
    }

    private function audit(
        string $correlationId,
        DateTimeImmutable $occurredAt,
        ?PlatformWorkforceCredentialData $credential,
        string $status,
    ): void {
        $succeeded = $status === PasswordBroker::RESET_LINK_SENT;

        try {
            $this->auditEntries->record(new AuditEntryData(
                self::auditEntryId(),
                $occurredAt,
                new AuditActorData(
                    $credential === null ? AuditActorType::Anonymous->value : AuditActorType::PlatformIdentity->value,
                    $credential?->platformIdentityId,
                ),
                null,
                'platform.authentication.password_reset_requested',
                new AuditTargetData('platform_session', $credential?->platformIdentityId),
                new AuditOutcomeData(
                    $succeeded ? AuditOutcomeType::Succeeded->value : AuditOutcomeType::Failed->value,
                    $succeeded ? null : $status,
                ),
                $correlationId,
                [],
            ));
        } catch (Throwable) {
            $this->logger->critical('platform.security.audit.emergency', [
                'correlation_id' => $correlationId,
                'actor_type' => $credential === null ? AuditActorType::Anonymous->value : AuditActorType::PlatformIdentity->value,
                'actor_identity_id' => $credential?->platformIdentityId,
                'action' => 'platform.authentication.password_reset_requested',
                'outcome' => $succeeded ? AuditOutcomeType::Succeeded->value : AuditOutcomeType::Failed->value,
                'reason_code' => $succeeded ? null : $status,
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
