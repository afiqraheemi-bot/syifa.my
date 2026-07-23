<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\EmailVerification;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditCorrelationIdResolverInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class SendPlatformEmailVerificationNotificationService
{
    public function __construct(
        private AuthFactory $auth,
        private AuditEntryRecorderInterface $auditEntries,
        private AuditCorrelationIdResolverInterface $correlationIds,
        private LoggerInterface $logger,
    ) {}

    /** Returns false only when no authenticated identity exists to notify. */
    public function execute(DateTimeImmutable $requestedAt): bool
    {
        $user = $this->auth->guard('platform_identity')->user();

        if (! $user instanceof MustVerifyEmail) {
            return false;
        }

        $identityId = (string) $user->getAuthIdentifier();

        if ($user->hasVerifiedEmail()) {
            $this->audit($requestedAt, $identityId, 'already_verified');

            return true;
        }

        $user->sendEmailVerificationNotification();
        $this->audit($requestedAt, $identityId, null);

        return true;
    }

    private function audit(DateTimeImmutable $occurredAt, string $identityId, ?string $reasonCode): void
    {
        $correlationId = $this->correlationIds->resolve();

        try {
            $this->auditEntries->record(new AuditEntryData(
                self::auditEntryId(),
                $occurredAt,
                new AuditActorData(AuditActorType::PlatformIdentity->value, $identityId),
                null,
                'platform.authentication.email_verification_requested',
                new AuditTargetData('platform_session', $identityId),
                new AuditOutcomeData(AuditOutcomeType::Succeeded->value, $reasonCode),
                $correlationId,
                [],
            ));
        } catch (Throwable) {
            $this->logger->critical('platform.security.audit.emergency', [
                'correlation_id' => $correlationId,
                'actor_type' => AuditActorType::PlatformIdentity->value,
                'actor_identity_id' => $identityId,
                'action' => 'platform.authentication.email_verification_requested',
                'outcome' => AuditOutcomeType::Succeeded->value,
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
