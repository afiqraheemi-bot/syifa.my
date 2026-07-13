<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Contracts\ClinicOwnerPasswordMatcherInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Entities\ClinicOwnerAuthority;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\ClinicOwnerAuthorityEstablished;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\ClinicOwnerAuthorityRevoked;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\ClinicOwnerAuthorityTransferred;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\TenantActivated;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\TenantDeletedOrAnonymized;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\TenantOffboardingStarted;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\TenantProvisioned;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\TenantReactivated;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events\TenantSuspended;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidClinicOwnerAuthorityTransitionException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidTenantLifecycleTransitionException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\TenantAdminRoutingLabelAlreadyAssignedException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityStatus;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerCredentialVerification;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerEmail;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentity;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantAdminRoutingLabel;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantLifecycleStatus;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantLifecycleTimestamps;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantReactivationReadiness;
use DateTimeImmutable;

final class Tenant
{
    /** @var array<string, ClinicOwnerAuthority> */
    private array $clinicOwnerAuthorities = [];

    /** @var list<object> */
    private array $domainEvents = [];

    private function __construct(
        public readonly TenantId $id,
        private TenantLifecycleStatus $status,
        private TenantLifecycleTimestamps $lifecycleTimestamps,
        private int $version,
        private ?TenantAdminRoutingLabel $adminRoutingLabel,
    ) {}

    public static function provision(TenantId $id, DateTimeImmutable $occurredAt): self
    {
        $tenant = new self(
            $id,
            TenantLifecycleStatus::Provisioning,
            new TenantLifecycleTimestamps($occurredAt),
            0,
            null,
        );
        $tenant->record(new TenantProvisioned($id->value, $occurredAt));

        return $tenant;
    }

    /** @param list<ClinicOwnerAuthority> $clinicOwnerAuthorities */
    public static function reconstitute(
        TenantId $id,
        TenantLifecycleStatus $status,
        TenantLifecycleTimestamps $lifecycleTimestamps,
        array $clinicOwnerAuthorities,
        int $version,
        ?TenantAdminRoutingLabel $adminRoutingLabel = null,
    ): self {
        if ($version < 1) {
            throw new InvalidTenantLifecycleTransitionException(
                'A persisted Tenant requires a positive aggregate version.',
            );
        }

        $tenant = new self($id, $status, $lifecycleTimestamps, $version, $adminRoutingLabel);

        foreach ($clinicOwnerAuthorities as $authority) {
            if (! $authority->belongsTo($id)) {
                throw new InvalidClinicOwnerAuthorityTransitionException(
                    'Clinic Owner Authority history cannot cross Tenant boundaries.',
                );
            }

            $tenant->assertAuthorityIdentifierIsUnused($authority->id);

            if ($authority->isActive() && $tenant->activeClinicOwnerAuthority() !== null) {
                throw new InvalidClinicOwnerAuthorityTransitionException(
                    'Reconstituted Tenant history may contain only one active Clinic Owner Authority.',
                );
            }

            $tenant->clinicOwnerAuthorities[$authority->id->value] = $authority;
        }

        return $tenant;
    }

    public function status(): TenantLifecycleStatus
    {
        return $this->status;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function lifecycleTimestamps(): TenantLifecycleTimestamps
    {
        return $this->lifecycleTimestamps;
    }

    public function adminRoutingLabel(): ?TenantAdminRoutingLabel
    {
        return $this->adminRoutingLabel;
    }

    public function assignAdminRoutingLabel(TenantAdminRoutingLabel $adminRoutingLabel): void
    {
        if ($this->adminRoutingLabel === null) {
            $this->adminRoutingLabel = $adminRoutingLabel;

            return;
        }

        if ($this->adminRoutingLabel->value === $adminRoutingLabel->value) {
            return;
        }

        throw new TenantAdminRoutingLabelAlreadyAssignedException(
            'Tenant Admin Routing Label cannot be replaced after initial assignment.',
        );
    }

    public function synchronizePersistenceVersion(int $version): void
    {
        if ($version !== $this->version + 1) {
            throw new InvalidTenantLifecycleTransitionException(
                'A Tenant persistence version must advance by exactly one.',
            );
        }

        $this->version = $version;
    }

    public function establishClinicOwnerAuthority(
        ClinicOwnerAuthorityId $authorityId,
        ClinicOwnerIdentity $identity,
        DateTimeImmutable $occurredAt,
    ): ClinicOwnerAuthority {
        $this->assertAuthorityMayBeEstablished();
        $this->assertAuthorityIdentifierIsUnused($authorityId);

        if ($this->activeClinicOwnerAuthority() !== null) {
            throw new InvalidClinicOwnerAuthorityTransitionException(
                'A Tenant may have only one active Clinic Owner Authority in Phase 1.',
            );
        }

        $authority = new ClinicOwnerAuthority(
            $authorityId,
            $this->id,
            $identity,
            ClinicOwnerAuthorityStatus::Active,
            $occurredAt,
        );

        $this->clinicOwnerAuthorities[$authorityId->value] = $authority;
        $this->record(new ClinicOwnerAuthorityEstablished(
            $this->id->value,
            $authorityId->value,
            $identity->id->value,
            $occurredAt,
        ));

        return $authority;
    }

    public function transferClinicOwnerAuthority(
        ClinicOwnerAuthorityId $currentAuthorityId,
        ClinicOwnerAuthorityId $newAuthorityId,
        ClinicOwnerIdentity $newIdentity,
        DateTimeImmutable $occurredAt,
    ): ClinicOwnerAuthority {
        $this->assertAuthorityMayBeEstablished();
        $this->assertAuthorityIdentifierIsUnused($newAuthorityId);
        $currentAuthority = $this->requireActiveAuthority($currentAuthorityId);

        if ($currentAuthority->clinicOwnerIdentity->id->value === $newIdentity->id->value) {
            throw new InvalidClinicOwnerAuthorityTransitionException(
                'Clinic Owner Authority cannot be transferred to the same identity.',
            );
        }

        $this->clinicOwnerAuthorities[$currentAuthorityId->value] = $this->revokedVersionOf(
            $currentAuthority,
            $occurredAt,
        );

        $newAuthority = new ClinicOwnerAuthority(
            $newAuthorityId,
            $this->id,
            $newIdentity,
            ClinicOwnerAuthorityStatus::Active,
            $occurredAt,
        );
        $this->clinicOwnerAuthorities[$newAuthorityId->value] = $newAuthority;
        $this->record(new ClinicOwnerAuthorityTransferred(
            $this->id->value,
            $currentAuthorityId->value,
            $newAuthorityId->value,
            $occurredAt,
        ));

        return $newAuthority;
    }

    public function revokeClinicOwnerAuthority(
        ClinicOwnerAuthorityId $authorityId,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->assertAuthorityGovernanceIsOpen();
        $authority = $this->requireActiveAuthority($authorityId);
        $this->clinicOwnerAuthorities[$authorityId->value] = $this->revokedVersionOf(
            $authority,
            $occurredAt,
        );
        $this->record(new ClinicOwnerAuthorityRevoked(
            $this->id->value,
            $authorityId->value,
            $authority->clinicOwnerIdentity->id->value,
            $occurredAt,
        ));
    }

    public function activeClinicOwnerAuthority(): ?ClinicOwnerAuthority
    {
        foreach ($this->clinicOwnerAuthorities as $authority) {
            if ($authority->isActive()) {
                return $authority;
            }
        }

        return null;
    }

    public function findClinicOwnerAuthority(ClinicOwnerAuthorityId $authorityId): ?ClinicOwnerAuthority
    {
        return $this->clinicOwnerAuthorities[$authorityId->value] ?? null;
    }

    public function findActiveClinicOwnerAuthorityByIdentity(
        ClinicOwnerIdentityId $identityId,
    ): ?ClinicOwnerAuthority {
        $authority = $this->activeClinicOwnerAuthority();

        if ($authority?->clinicOwnerIdentity->id->value !== $identityId->value) {
            return null;
        }

        return $authority;
    }

    public function findActiveClinicOwnerAuthorityByEmail(
        ClinicOwnerEmail $email,
    ): ?ClinicOwnerAuthority {
        $authority = $this->activeClinicOwnerAuthority();

        if ($authority?->clinicOwnerIdentity->email->value !== $email->value) {
            return null;
        }

        return $authority;
    }

    public function verifyClinicOwnerCredential(
        ClinicOwnerEmail $email,
        ClinicOwnerPasswordMatcherInterface $passwordMatcher,
        DateTimeImmutable $occurredAt,
    ): ClinicOwnerCredentialVerification {
        $authority = $this->findActiveClinicOwnerAuthorityByEmail($email);

        if ($authority === null || ! $this->permitsClinicOwnerAuthenticatedAccess()) {
            return new ClinicOwnerCredentialVerification(false, false);
        }

        $state = $authority->credentialState();

        if (! $state->isUsable() || $state->isLockedAt($occurredAt)) {
            return new ClinicOwnerCredentialVerification(false, false);
        }

        if (! $state->matches($passwordMatcher)) {
            $this->recordFailedClinicOwnerCredentialVerification($authority->id, $occurredAt);

            return new ClinicOwnerCredentialVerification(false, true);
        }

        $stateChanged = $state->failedAttemptCount !== 0 || $state->lockoutUntil !== null;

        if ($stateChanged) {
            $this->recordSuccessfulClinicOwnerCredentialVerification($authority->id, $occurredAt);
        }

        return new ClinicOwnerCredentialVerification(
            true,
            $stateChanged,
            $authority->id->value,
            $authority->clinicOwnerIdentity->id->value,
            $state->isEmailVerified(),
        );
    }

    public function recordFailedClinicOwnerCredentialVerification(
        ClinicOwnerAuthorityId $authorityId,
        DateTimeImmutable $occurredAt,
    ): void {
        $authority = $this->requireCredentialEligibleAuthority($authorityId, $occurredAt);
        $this->clinicOwnerAuthorities[$authorityId->value] = $authority->withCredentialState(
            $authority->credentialState()->recordFailure($occurredAt),
        );
    }

    public function recordSuccessfulClinicOwnerCredentialVerification(
        ClinicOwnerAuthorityId $authorityId,
        DateTimeImmutable $occurredAt,
    ): void {
        $authority = $this->requireCredentialEligibleAuthority($authorityId, $occurredAt);
        $this->clinicOwnerAuthorities[$authorityId->value] = $authority->withCredentialState(
            $authority->credentialState()->recordSuccess(),
        );
    }

    public function verifyClinicOwnerEmail(
        ClinicOwnerAuthorityId $authorityId,
        DateTimeImmutable $verifiedAt,
    ): void {
        $authority = $this->requireActiveAuthority($authorityId);
        $this->clinicOwnerAuthorities[$authorityId->value] = $authority->withCredentialState(
            $authority->credentialState()->verifyEmail($verifiedAt),
        );
    }

    public function changeClinicOwnerPasswordHash(
        ClinicOwnerAuthorityId $authorityId,
        string $passwordHash,
    ): void {
        $authority = $this->requireActiveAuthority($authorityId);
        $this->clinicOwnerAuthorities[$authorityId->value] = $authority->withCredentialState(
            $authority->credentialState()->changePasswordHash($passwordHash),
        );
    }

    /** @return list<ClinicOwnerAuthority> */
    public function clinicOwnerAuthorityHistory(): array
    {
        return array_values($this->clinicOwnerAuthorities);
    }

    public function permitsClinicOwnerAuthenticatedAccess(): bool
    {
        return in_array($this->status, [
            TenantLifecycleStatus::Active,
            TenantLifecycleStatus::Reactivated,
        ], true);
    }

    public function activate(DateTimeImmutable $occurredAt): void
    {
        $this->assertStatusIs(TenantLifecycleStatus::Provisioning, 'activate');
        $this->assertHasActiveAuthority('activate');
        $this->status = TenantLifecycleStatus::Active;
        $this->lifecycleTimestamps = $this->lifecycleTimestamps->activated($occurredAt);
        $this->record(new TenantActivated($this->id->value, $occurredAt));
    }

    public function suspend(DateTimeImmutable $occurredAt): void
    {
        if (! in_array($this->status, [TenantLifecycleStatus::Active, TenantLifecycleStatus::Reactivated], true)) {
            throw $this->invalidLifecycleTransition('suspend');
        }

        $this->status = TenantLifecycleStatus::Suspended;
        $this->lifecycleTimestamps = $this->lifecycleTimestamps->suspended($occurredAt);
        $this->record(new TenantSuspended($this->id->value, $occurredAt));
    }

    public function reactivate(
        TenantReactivationReadiness $readiness,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->assertStatusIs(TenantLifecycleStatus::Suspended, 'reactivate');
        $this->assertHasActiveAuthority('reactivate');

        if (! $readiness->isSatisfied()) {
            throw new InvalidTenantLifecycleTransitionException(
                'Tenant reactivation requires validated subscription, domain, owner, assignments, and pending work.',
            );
        }

        $this->status = TenantLifecycleStatus::Reactivated;
        $this->lifecycleTimestamps = $this->lifecycleTimestamps->reactivated($occurredAt);
        $this->record(new TenantReactivated($this->id->value, $occurredAt));
    }

    public function beginOffboarding(DateTimeImmutable $occurredAt): void
    {
        if (! in_array($this->status, [
            TenantLifecycleStatus::Active,
            TenantLifecycleStatus::Suspended,
            TenantLifecycleStatus::Reactivated,
        ], true)) {
            throw $this->invalidLifecycleTransition('begin offboarding');
        }

        $this->status = TenantLifecycleStatus::Offboarding;
        $this->lifecycleTimestamps = $this->lifecycleTimestamps->offboardingStarted($occurredAt);
        $this->record(new TenantOffboardingStarted($this->id->value, $occurredAt));
    }

    public function delete(DateTimeImmutable $occurredAt): void
    {
        $this->completeOffboarding(TenantLifecycleStatus::Deleted, $occurredAt);
    }

    public function anonymize(DateTimeImmutable $occurredAt): void
    {
        $this->completeOffboarding(TenantLifecycleStatus::Anonymized, $occurredAt);
    }

    /** @return list<object> */
    public function releaseDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    private function completeOffboarding(
        TenantLifecycleStatus $outcome,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->assertStatusIs(TenantLifecycleStatus::Offboarding, $outcome->value);
        $this->status = $outcome;
        $this->lifecycleTimestamps = $this->lifecycleTimestamps->closed($occurredAt);
        $this->record(new TenantDeletedOrAnonymized(
            $this->id->value,
            $outcome->value,
            $occurredAt,
        ));
    }

    private function requireActiveAuthority(
        ClinicOwnerAuthorityId $authorityId,
    ): ClinicOwnerAuthority {
        $authority = $this->findClinicOwnerAuthority($authorityId);

        if ($authority === null || ! $authority->isActive()) {
            throw new InvalidClinicOwnerAuthorityTransitionException(
                'The requested active Clinic Owner Authority does not belong to this Tenant.',
            );
        }

        return $authority;
    }

    private function revokedVersionOf(
        ClinicOwnerAuthority $authority,
        DateTimeImmutable $occurredAt,
    ): ClinicOwnerAuthority {
        return new ClinicOwnerAuthority(
            $authority->id,
            $this->id,
            $authority->clinicOwnerIdentity,
            ClinicOwnerAuthorityStatus::Revoked,
            $authority->establishedAt,
            $occurredAt,
            $authority->credentialState(),
        );
    }

    private function requireCredentialEligibleAuthority(
        ClinicOwnerAuthorityId $authorityId,
        DateTimeImmutable $occurredAt,
    ): ClinicOwnerAuthority {
        $authority = $this->requireActiveAuthority($authorityId);

        if (! $this->permitsClinicOwnerAuthenticatedAccess()
            || ! $authority->credentialState()->isUsable()
            || $authority->credentialState()->isLockedAt($occurredAt)) {
            throw new InvalidClinicOwnerAuthorityTransitionException(
                'Clinic Owner Authority is not eligible for credential checking.',
            );
        }

        return $authority;
    }

    private function assertAuthorityIdentifierIsUnused(ClinicOwnerAuthorityId $authorityId): void
    {
        if ($this->findClinicOwnerAuthority($authorityId) !== null) {
            throw new InvalidClinicOwnerAuthorityTransitionException(
                'A Clinic Owner Authority identifier cannot be reused.',
            );
        }
    }

    private function assertAuthorityMayBeEstablished(): void
    {
        $this->assertAuthorityGovernanceIsOpen();

        if ($this->status === TenantLifecycleStatus::Offboarding) {
            throw new InvalidClinicOwnerAuthorityTransitionException(
                'Clinic Owner Authority cannot be established or transferred during offboarding.',
            );
        }
    }

    private function assertAuthorityGovernanceIsOpen(): void
    {
        if (in_array($this->status, [TenantLifecycleStatus::Deleted, TenantLifecycleStatus::Anonymized], true)) {
            throw new InvalidClinicOwnerAuthorityTransitionException(
                'Clinic Owner Authority cannot change after Tenant closure.',
            );
        }
    }

    private function assertHasActiveAuthority(string $transition): void
    {
        if ($this->activeClinicOwnerAuthority() === null) {
            throw new InvalidTenantLifecycleTransitionException(
                sprintf('Tenant cannot %s without an active Clinic Owner Authority.', $transition),
            );
        }
    }

    private function assertStatusIs(TenantLifecycleStatus $expected, string $transition): void
    {
        if ($this->status !== $expected) {
            throw $this->invalidLifecycleTransition($transition);
        }
    }

    private function invalidLifecycleTransition(string $transition): InvalidTenantLifecycleTransitionException
    {
        return new InvalidTenantLifecycleTransitionException(sprintf(
            'Tenant in %s state cannot %s.',
            $this->status->value,
            $transition,
        ));
    }

    private function record(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
