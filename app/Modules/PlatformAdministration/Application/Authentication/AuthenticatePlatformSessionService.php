<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\Authentication;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditCorrelationIdResolverInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionAuthenticationInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionStoreInterface;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityData;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityLookupInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialData;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialLookupInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\PlatformIdentityRole;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\PlatformIdentityStatus;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SensitiveParameter;
use Throwable;

/**
 * The real verification entry point is now Laravel's native, stateful Guard
 * (`Auth::guard('platform_identity')->attempt()`) — never a bespoke password
 * check. The Guard's own UserProvider (`PlatformIdentityUserProvider`)
 * delegates the actual hash comparison, lockout, and timing-safe unknown-email
 * handling to the pre-existing `CredentialVerificationInterface` Infrastructure
 * adapter, so none of that hardening is duplicated or re-implemented here —
 * this service only adds the business rules `attempt()` cannot know about
 * (email verification, identity status, role allow-list) and audits every
 * branch exactly as before.
 */
final readonly class AuthenticatePlatformSessionService implements PlatformSessionAuthenticationInterface
{
    public function __construct(
        private AuthFactory $auth,
        private PlatformWorkforceCredentialLookupInterface $credentialLookup,
        private PlatformIdentityLookupInterface $identities,
        private PlatformSessionStoreInterface $sessions,
        private AuditEntryRecorderInterface $auditEntries,
        private AuditCorrelationIdResolverInterface $correlationIds,
        private LoggerInterface $logger,
    ) {}

    public function authenticate(
        string $email,
        #[SensitiveParameter] string $plainPassword,
        DateTimeImmutable $attemptedAt,
        bool $remember = false,
        bool $establishSession = true,
    ): ?PlatformPrincipal {
        $attemptedAtUtc = $this->utc($attemptedAt);
        $correlationId = $this->correlationIds->resolve();
        $credential = $this->credentialLookup->findByNormalizedEmail($email);

        // The Guard's own credential lookup (`retrieveByCredentials`) is
        // deliberately fed by the Contracts-level lookup rather than a real
        // Eloquent row, so this call is never remembered here — Laravel's
        // recaller cookie needs a genuine password_hash, which only exists on
        // a real row. `establish()` resolves that real row and finishes the
        // "remember" registration itself, once verification has succeeded.
        $guard = $this->guard();
        $verified = $guard->attempt(['email' => $email, 'password' => $plainPassword]);

        if (! $verified) {
            $reasonCode = $this->failureReason($credential, $attemptedAtUtc);
            $this->auditAuthenticationFailure(
                $correlationId,
                $attemptedAtUtc,
                $reasonCode,
                $credential,
                null,
            );

            return null;
        }

        if ($credential === null) {
            $guard->logout();
            $this->auditAuthenticationFailure(
                $correlationId,
                $attemptedAtUtc,
                'invalid_credentials',
                null,
                null,
            );

            return null;
        }

        if ($credential->emailVerified === false) {
            $guard->logout();
            $identity = $this->identities->findById($credential->platformIdentityId);
            $this->auditAuthenticationFailure(
                $correlationId,
                $attemptedAtUtc,
                'email_not_verified',
                $credential,
                $identity,
            );

            return null;
        }

        $identity = $this->identities->findById($credential->platformIdentityId);

        if ($identity === null || $identity->status !== PlatformIdentityStatus::Active->value) {
            $guard->logout();
            $this->auditAuthenticationFailure(
                $correlationId,
                $attemptedAtUtc,
                $identity === null ? 'identity_not_found' : 'identity_inactive',
                $credential,
                $identity,
            );

            return null;
        }

        if (! in_array($identity->role, [
            PlatformIdentityRole::WebsiteDesigner->value,
            PlatformIdentityRole::SuperAdmin->value,
        ], true)) {
            $guard->logout();
            $this->auditAuthenticationFailure(
                $correlationId,
                $attemptedAtUtc,
                'role_not_allowed',
                $credential,
                $identity,
            );

            return null;
        }

        $principal = new PlatformPrincipal(
            $identity->id,
            $identity->role,
            $identity->name,
        );

        if ($establishSession) {
            if (! $this->auditAuthenticationSuccess($correlationId, $attemptedAtUtc, $identity, $remember)) {
                $guard->logout();

                return null;
            }

            $this->sessions->establish($principal, $attemptedAt, $remember);
        } else {
            // A valid password is only the first factor for platform workforce
            // identities. Never leave Laravel's native guard authenticated
            // while the server-owned MFA challenge is pending.
            $guard->logout();
        }

        return $principal;
    }

    private function guard(): StatefulGuard
    {
        $guard = $this->auth->guard('platform_identity');

        if (! $guard instanceof StatefulGuard) {
            throw new RuntimeException('The platform_identity guard must be a stateful (session) guard.');
        }

        return $guard;
    }

    private function auditAuthenticationSuccess(
        string $correlationId,
        DateTimeImmutable $occurredAt,
        PlatformIdentityData $identity,
        bool $remember,
    ): bool {
        try {
            $this->auditEntries->record(new AuditEntryData(
                self::auditEntryId(),
                $occurredAt,
                new AuditActorData(AuditActorType::PlatformIdentity->value, $identity->id),
                null,
                $remember ? 'platform.authentication.remember_me_login' : 'platform.authentication.login',
                new AuditTargetData('platform_session', $identity->id),
                new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
                $correlationId,
                ['actor_role' => $identity->role],
            ));

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function auditAuthenticationFailure(
        string $correlationId,
        DateTimeImmutable $occurredAt,
        string $reasonCode,
        ?PlatformWorkforceCredentialData $credential,
        ?PlatformIdentityData $identity,
    ): void {
        try {
            $this->auditEntries->record(new AuditEntryData(
                self::auditEntryId(),
                $occurredAt,
                new AuditActorData(
                    $credential === null ? AuditActorType::Anonymous->value : AuditActorType::PlatformIdentity->value,
                    $credential?->platformIdentityId,
                ),
                null,
                'platform.authentication.login',
                new AuditTargetData('platform_session', $credential?->platformIdentityId),
                new AuditOutcomeData(AuditOutcomeType::Failed->value, $reasonCode),
                $correlationId,
                $identity === null ? [] : ['actor_role' => $identity->role],
            ));
        } catch (Throwable) {
            $this->emergencySecurityLog(
                $correlationId,
                $credential === null ? AuditActorType::Anonymous->value : AuditActorType::PlatformIdentity->value,
                $credential?->platformIdentityId,
                'platform.authentication.login',
                AuditOutcomeType::Failed->value,
                $reasonCode,
                $occurredAt,
            );
        }
    }

    private function failureReason(?PlatformWorkforceCredentialData $credential, DateTimeImmutable $attemptedAt): string
    {
        if ($credential === null) {
            return 'invalid_credentials';
        }

        if ($credential->emailVerified === false) {
            return 'email_not_verified';
        }

        if ($credential->lockoutUntil instanceof DateTimeImmutable && $credential->lockoutUntil > $attemptedAt) {
            return 'account_locked';
        }

        if ($credential->accountStatus !== 'active') {
            return 'account_inactive';
        }

        return 'invalid_credentials';
    }

    private function emergencySecurityLog(
        string $correlationId,
        string $actorType,
        ?string $actorIdentityId,
        string $action,
        string $outcome,
        string $reasonCode,
        DateTimeImmutable $timestamp,
    ): void {
        $this->logger->critical('platform.security.audit.emergency', [
            'correlation_id' => $correlationId,
            'actor_type' => $actorType,
            'actor_identity_id' => $actorIdentityId,
            'action' => $action,
            'outcome' => $outcome,
            'reason_code' => $reasonCode,
            'timestamp' => $timestamp->format('Y-m-d\TH:i:s\Z'),
        ]);
    }

    private function utc(DateTimeImmutable $instant): DateTimeImmutable
    {
        return $instant->setTimezone(new DateTimeZone('UTC'));
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
