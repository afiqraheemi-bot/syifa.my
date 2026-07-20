<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\Authentication;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditCorrelationIdResolverInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionStoreInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class LogoutPlatformSessionService
{
    public function __construct(
        private PlatformPrincipalResolverInterface $principals,
        private PlatformSessionStoreInterface $sessions,
        private AuditEntryRecorderInterface $auditEntries,
        private AuditCorrelationIdResolverInterface $correlationIds,
        private LoggerInterface $logger,
    ) {}

    public function execute(): void
    {
        $completedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $correlationId = $this->correlationIds->resolve();
        $principal = $this->principals->resolve($completedAt);

        $this->sessions->invalidate();

        try {
            $this->auditEntries->record(new AuditEntryData(
                self::auditEntryId(),
                $completedAt,
                new AuditActorData(
                    $principal === null ? AuditActorType::Anonymous->value : AuditActorType::PlatformIdentity->value,
                    $principal?->platformIdentityId,
                ),
                null,
                'platform.authentication.logout',
                new AuditTargetData('platform_session', $principal?->platformIdentityId),
                new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
                $correlationId,
                $principal === null ? [] : ['actor_role' => $principal->role],
            ));
        } catch (Throwable) {
            $this->logger->critical('platform.security.audit.emergency', [
                'correlation_id' => $correlationId,
                'actor_type' => $principal === null ? AuditActorType::Anonymous->value : AuditActorType::PlatformIdentity->value,
                'actor_identity_id' => $principal?->platformIdentityId,
                'action' => 'platform.authentication.logout',
                'outcome' => AuditOutcomeType::Succeeded->value,
                'reason_code' => 'audit_entry_recording_failed',
                'timestamp' => $completedAt->format('Y-m-d\TH:i:s\Z'),
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
