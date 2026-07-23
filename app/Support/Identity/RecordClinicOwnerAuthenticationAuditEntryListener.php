<?php

declare(strict_types=1);

namespace App\Support\Identity;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditCorrelationIdResolverInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationRejected;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationSucceeded;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Sprint 3A Phase 5: closes a pre-existing gap — `AuthenticateClinicOwnerService`
 * has always dispatched `ClinicOwnerAuthenticationSucceeded`/`Rejected` as
 * real Laravel events, but nothing ever consumed them, so no Clinic Owner
 * login attempt was ever audited. `AuditActorType::ClinicOwner` already
 * existed in the shared Platform audit trail for exactly this purpose. This
 * listener is the only place Clinic Owner authentication ever crosses into
 * `PlatformAdministration`'s audit boundary — deliberately living outside
 * both modules (`app/Support/Identity`) so neither owns a dependency on the
 * other; only this one shared identity boundary does.
 */
final readonly class RecordClinicOwnerAuthenticationAuditEntryListener
{
    public function __construct(
        private AuditEntryRecorderInterface $auditEntries,
        private AuditCorrelationIdResolverInterface $correlationIds,
        private LoggerInterface $logger,
    ) {}

    public function handleSucceeded(ClinicOwnerAuthenticationSucceeded $signal): void
    {
        $this->record(
            $signal->occurredAt,
            $signal->authorityId,
            AuditOutcomeType::Succeeded->value,
            null,
        );
    }

    public function handleRejected(ClinicOwnerAuthenticationRejected $signal): void
    {
        $this->record($signal->occurredAt, null, AuditOutcomeType::Failed->value, 'invalid_credentials');
    }

    private function record(
        DateTimeImmutable $occurredAt,
        ?string $authorityId,
        string $outcome,
        ?string $reasonCode,
    ): void {
        $correlationId = $this->correlationIds->resolve();

        try {
            $this->auditEntries->record(new AuditEntryData(
                self::auditEntryId(),
                $occurredAt,
                new AuditActorData(
                    $authorityId === null ? AuditActorType::Anonymous->value : AuditActorType::ClinicOwner->value,
                    $authorityId,
                ),
                null,
                'clinic_owner.authentication.login',
                new AuditTargetData('clinic_owner_session', $authorityId),
                new AuditOutcomeData($outcome, $reasonCode),
                $correlationId,
                [],
            ));
        } catch (Throwable) {
            $this->logger->critical('tenant_management.security.audit.emergency', [
                'correlation_id' => $correlationId,
                'actor_type' => $authorityId === null ? AuditActorType::Anonymous->value : AuditActorType::ClinicOwner->value,
                'actor_identity_id' => $authorityId,
                'action' => 'clinic_owner.authentication.login',
                'outcome' => $outcome,
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
