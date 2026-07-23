<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\PasswordReset;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditCorrelationIdResolverInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialPasswordWriterInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Throwable;

final readonly class ResetPlatformPasswordService
{
    public function __construct(
        private PasswordBrokerFactory $passwords,
        private PlatformWorkforceCredentialPasswordWriterInterface $credentials,
        private AuditEntryRecorderInterface $auditEntries,
        private AuditCorrelationIdResolverInterface $correlationIds,
        private LoggerInterface $logger,
    ) {}

    public function execute(
        string $email,
        string $token,
        #[SensitiveParameter] string $newPassword,
        DateTimeImmutable $resetAt,
    ): bool {
        $correlationId = $this->correlationIds->resolve();
        $identityId = null;

        $status = $this->passwords->broker('platform_identities')->reset(
            ['email' => $email, 'token' => $token, 'password' => $newPassword],
            function (Authenticatable $user) use (&$identityId, $newPassword, $resetAt): void {
                $identityId = (string) $user->getAuthIdentifier();
                $this->credentials->updatePassword($identityId, $newPassword, $resetAt);
            },
        );

        $succeeded = $status === PasswordBroker::PASSWORD_RESET;
        $this->audit($correlationId, $resetAt, $identityId, $status, $succeeded);

        return $succeeded;
    }

    private function audit(
        string $correlationId,
        DateTimeImmutable $occurredAt,
        ?string $identityId,
        string $status,
        bool $succeeded,
    ): void {
        try {
            $this->auditEntries->record(new AuditEntryData(
                self::auditEntryId(),
                $occurredAt,
                new AuditActorData(
                    $identityId === null ? AuditActorType::Anonymous->value : AuditActorType::PlatformIdentity->value,
                    $identityId,
                ),
                null,
                'platform.authentication.password_reset_succeeded',
                new AuditTargetData('platform_session', $identityId),
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
                'actor_type' => $identityId === null ? AuditActorType::Anonymous->value : AuditActorType::PlatformIdentity->value,
                'actor_identity_id' => $identityId,
                'action' => 'platform.authentication.password_reset_succeeded',
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
