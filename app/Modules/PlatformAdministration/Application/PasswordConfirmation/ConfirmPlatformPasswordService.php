<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\PasswordConfirmation;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditCorrelationIdResolverInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityLookupInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Session\Session;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Throwable;

/**
 * Re-verifies the already-authenticated identity's password without
 * disturbing their existing session identity — `Guard::validate()` (not
 * `attempt()`/`login()`) is the correct native primitive here, since a
 * confirmation must never re-establish or replace the current login.
 * `Illuminate\Auth\Middleware\RequirePassword` reads the exact session key
 * this service writes (`auth.password_confirmed_at`), so no custom
 * middleware is needed to enforce the resulting confirmation window.
 */
final readonly class ConfirmPlatformPasswordService
{
    private const string SESSION_KEY = 'auth.password_confirmed_at';

    public function __construct(
        private AuthFactory $auth,
        private Session $session,
        private PlatformIdentityLookupInterface $identities,
        private AuditEntryRecorderInterface $auditEntries,
        private AuditCorrelationIdResolverInterface $correlationIds,
        private LoggerInterface $logger,
    ) {}

    public function execute(#[SensitiveParameter] string $plainPassword, DateTimeImmutable $confirmedAt): bool
    {
        $guard = $this->auth->guard('platform_identity');
        $user = $guard->user();

        if ($user === null) {
            return false;
        }

        $identityId = (string) $user->getAuthIdentifier();
        $identity = $this->identities->findById($identityId);

        if ($identity === null) {
            return false;
        }

        $confirmed = $guard->validate(['email' => $identity->email, 'password' => $plainPassword]);

        if (! $confirmed) {
            $this->audit($confirmedAt, $identityId, false);

            return false;
        }

        $this->session->put(self::SESSION_KEY, $confirmedAt->getTimestamp());
        $this->audit($confirmedAt, $identityId, true);

        return true;
    }

    private function audit(DateTimeImmutable $occurredAt, string $identityId, bool $succeeded): void
    {
        $correlationId = $this->correlationIds->resolve();

        try {
            $this->auditEntries->record(new AuditEntryData(
                self::auditEntryId(),
                $occurredAt,
                new AuditActorData(AuditActorType::PlatformIdentity->value, $identityId),
                null,
                'platform.authentication.password_confirmed',
                new AuditTargetData('platform_session', $identityId),
                new AuditOutcomeData(
                    $succeeded ? AuditOutcomeType::Succeeded->value : AuditOutcomeType::Failed->value,
                    $succeeded ? null : 'invalid_credentials',
                ),
                $correlationId,
                [],
            ));
        } catch (Throwable) {
            $this->logger->critical('platform.security.audit.emergency', [
                'correlation_id' => $correlationId,
                'actor_type' => AuditActorType::PlatformIdentity->value,
                'actor_identity_id' => $identityId,
                'action' => 'platform.authentication.password_confirmed',
                'outcome' => $succeeded ? AuditOutcomeType::Succeeded->value : AuditOutcomeType::Failed->value,
                'reason_code' => $succeeded ? null : 'invalid_credentials',
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
