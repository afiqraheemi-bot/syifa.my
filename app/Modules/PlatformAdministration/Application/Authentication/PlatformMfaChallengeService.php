<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\Authentication;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditCorrelationIdResolverInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Contracts\Authentication\PendingPlatformAuthenticationData;
use App\Modules\PlatformAdministration\Contracts\Authentication\PendingPlatformAuthenticationStoreInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformMfaChallengeData;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformMfaChallengeInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformMfaEnrollmentRepositoryInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionStoreInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Str;
use OTPHP\InternalClock;
use OTPHP\TOTP;
use Throwable;

final readonly class PlatformMfaChallengeService implements PlatformMfaChallengeInterface
{
    private const int CHALLENGE_MINUTES = 5;

    public function __construct(
        private PlatformMfaEnrollmentRepositoryInterface $enrollments,
        private PendingPlatformAuthenticationStoreInterface $pending,
        private PlatformSessionStoreInterface $sessions,
        private AuditEntryRecorderInterface $audit,
        private AuditCorrelationIdResolverInterface $correlations,
        private Encrypter $encrypter,
        private bool $localDemoMfaEnabled = false,
        private string $localDemoMfaCode = '',
        /** @var list<string> */
        private array $localDemoPlatformIdentityIds = [],
    ) {}

    public function begin(
        PlatformPrincipal $principal,
        string $normalizedEmail,
        bool $remember,
        DateTimeImmutable $at,
    ): PlatformMfaChallengeData {
        $enrollment = $this->enrollments->find($principal->platformIdentityId);
        $secret = null;
        $encryptedSecret = null;

        if ($enrollment === null) {
            $label = $this->label($normalizedEmail, $principal);
            $totp = TOTP::generate(new InternalClock, 20)
                ->withIssuer('SYIFA.my')
                ->withLabel($label);
            $secret = $totp->getSecret();
            $encryptedSecret = $this->encrypter->encryptString($secret);
        }

        $this->pending->establish(new PendingPlatformAuthenticationData(
            $principal,
            $remember,
            $at->setTimezone(new DateTimeZone('UTC')),
            $at->add(new DateInterval('PT'.self::CHALLENGE_MINUTES.'M'))
                ->setTimezone(new DateTimeZone('UTC')),
            $encryptedSecret,
        ));

        $this->record($principal, 'platform.authentication.mfa_challenge_issued', 'succeeded', null, $at);

        if ($secret === null) {
            return new PlatformMfaChallengeData('mfa_required');
        }

        $label = $this->label($normalizedEmail, $principal);
        $totp = TOTP::createFromSecret($secret, new InternalClock)
            ->withIssuer('SYIFA.my')
            ->withLabel($label);

        return new PlatformMfaChallengeData(
            'mfa_enrollment_required',
            $secret,
            $totp->getProvisioningUri(),
        );
    }

    public function complete(string $code, DateTimeImmutable $at): ?PlatformPrincipal
    {
        $pending = $this->pending->current();
        if ($pending === null || $pending->expiresAt <= $at || preg_match('/^[0-9]{6}$/', $code) !== 1) {
            $this->pending->clear();

            return null;
        }

        if ($this->acceptsLocalDemoCode($pending->principal, $code)) {
            $this->record($pending->principal, 'platform.authentication.local_demo_mfa_challenge', 'succeeded', null, $at);
            $this->pending->clear();
            $this->sessions->establish($pending->principal, $at, $pending->remember);

            return $pending->principal;
        }

        $enrollment = $this->enrollments->find($pending->principal->platformIdentityId);
        $encryptedSecret = $enrollment === null
            ? $pending->encryptedEnrollmentSecret
            : $enrollment->encryptedTotpSecret;
        if ($encryptedSecret === null) {
            $this->pending->clear();

            return null;
        }

        try {
            $secret = $this->encrypter->decryptString($encryptedSecret);
            $verifiedStep = $this->verifiedTimeStep($secret, $code, $at);
        } catch (Throwable) {
            $verifiedStep = null;
        }

        $lastVerifiedStep = $enrollment === null ? -1 : ($enrollment->lastVerifiedTimeStep ?? -1);
        if ($verifiedStep === null || $lastVerifiedStep >= $verifiedStep) {
            $this->record($pending->principal, 'platform.authentication.mfa_challenge', 'failed', 'invalid_or_replayed_code', $at);

            return null;
        }

        try {
            if ($enrollment === null) {
                $this->enrollments->enroll(
                    $pending->principal->platformIdentityId,
                    $encryptedSecret,
                    $verifiedStep,
                    $at,
                );
                $this->record($pending->principal, 'platform.authentication.mfa_enrolled', 'succeeded', null, $at);
            } elseif (! $this->enrollments->recordVerification(
                $pending->principal->platformIdentityId,
                $enrollment->version,
                $verifiedStep,
                $at,
            )) {
                return null;
            }

            $this->record($pending->principal, 'platform.authentication.mfa_challenge', 'succeeded', null, $at);
        } catch (Throwable) {
            return null;
        }

        $this->pending->clear();
        $this->sessions->establish($pending->principal, $at, $pending->remember);

        return $pending->principal;
    }

    private function acceptsLocalDemoCode(PlatformPrincipal $principal, string $code): bool
    {
        return $this->localDemoMfaEnabled
            && $this->localDemoMfaCode !== ''
            && hash_equals($this->localDemoMfaCode, $code)
            && in_array($principal->platformIdentityId, $this->localDemoPlatformIdentityIds, true);
    }

    private function verifiedTimeStep(string $secret, string $code, DateTimeImmutable $at): ?int
    {
        if ($secret === '' || $code === '') {
            return null;
        }

        $totp = TOTP::createFromSecret($secret, new InternalClock);
        $timestamp = $at->getTimestamp();

        foreach ([$timestamp - 15, $timestamp, $timestamp + 15] as $candidate) {
            if ($candidate >= 0 && $totp->verify($code, $candidate)) {
                return intdiv($candidate, $totp->getPeriod());
            }
        }

        return null;
    }

    /** @return non-empty-string */
    private function label(string $normalizedEmail, PlatformPrincipal $principal): string
    {
        $label = mb_strtolower(trim($normalizedEmail));
        if ($label === '') {
            $label = trim($principal->platformIdentityId);
        }
        if ($label === '') {
            throw new \LogicException('Platform MFA requires an identity label.');
        }

        return $label;
    }

    private function record(
        PlatformPrincipal $principal,
        string $action,
        string $outcome,
        ?string $reason,
        DateTimeImmutable $at,
    ): void {
        $this->audit->record(new AuditEntryData(
            Str::uuid()->toString(),
            $at->setTimezone(new DateTimeZone('UTC')),
            new AuditActorData(AuditActorType::PlatformIdentity->value, $principal->platformIdentityId),
            null,
            $action,
            new AuditTargetData('platform_session', $principal->platformIdentityId),
            new AuditOutcomeData($outcome === 'succeeded'
                ? AuditOutcomeType::Succeeded->value
                : AuditOutcomeType::Failed->value, $reason),
            $this->correlations->resolve(),
            ['actor_role' => $principal->role],
        ));
    }
}
