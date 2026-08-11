<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Domain;

use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationCancelled;
use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationDecisionRecorded;
use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationExpired;
use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationProvisioned;
use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationResubmitted;
use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationReviewStarted;
use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationStarted;
use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationSubmitted;
use App\Modules\ClinicRegistration\Domain\Exceptions\InvalidClinicRegistrationTransitionException;
use App\Modules\ClinicRegistration\Domain\ValueObjects\ClinicRegistrationProfile;
use App\Modules\ClinicRegistration\Domain\ValueObjects\CommercialSelectionReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\DeclarationAcceptance;
use App\Modules\ClinicRegistration\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\ProvisionedTenantReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationDecisionOutcome;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationId;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationStatus;
use App\Modules\ClinicRegistration\Domain\ValueObjects\TenantId;
use DateTimeImmutable;

final class ClinicRegistration
{
    /**
     * @param  list<DeclarationAcceptance>  $declarations
     * @param  list<RegistrationDecision>  $decisions
     * @param  list<object>  $recordedEvents
     */
    public function __construct(
        public RegistrationId $id,
        public PlatformIdentityReference $platformIdentity,
        public RegistrationStatus $status,
        public ClinicRegistrationProfile $profile,
        public array $declarations,
        public CommercialSelectionReference $commercialSelection,
        public string $correlationReference,
        public ?TenantId $reservedTenantId,
        public ?ProvisionedTenantReference $provisionedTenant,
        public ?DateTimeImmutable $submittedAt,
        public ?DateTimeImmutable $provisionedAt,
        public ?DateTimeImmutable $cancelledAt,
        public ?DateTimeImmutable $expiredAt,
        public array $decisions = [],
        private int $version = 0,
        private array $recordedEvents = [],
    ) {}

    public static function start(
        RegistrationId $id,
        PlatformIdentityReference $platformIdentity,
        DateTimeImmutable $occurredAt,
    ): self {
        $registration = new self(
            id: $id,
            platformIdentity: $platformIdentity,
            status: RegistrationStatus::Draft,
            profile: new ClinicRegistrationProfile(null, null, null, null),
            declarations: [],
            commercialSelection: new CommercialSelectionReference(null, null, null),
            correlationReference: $id->value,
            reservedTenantId: null,
            provisionedTenant: null,
            submittedAt: null,
            provisionedAt: null,
            cancelledAt: null,
            expiredAt: null,
            decisions: [],
        );
        $registration->record(new ClinicRegistrationStarted($id->value, $platformIdentity->value, $occurredAt));

        return $registration;
    }

    /** @param list<DeclarationAcceptance> $declarations */
    public function updateDraft(
        ClinicRegistrationProfile $profile,
        array $declarations,
        CommercialSelectionReference $commercialSelection,
    ): void {
        $this->assertMutableDraft();

        $this->profile = $profile;
        $this->declarations = $declarations;
        $this->commercialSelection = $commercialSelection;
    }

    public function reviseProfileByAdministrator(ClinicRegistrationProfile $profile): void
    {
        if (! $this->isEditableByAdministrator()) {
            throw new InvalidClinicRegistrationTransitionException(
                'Approved, provisioned, rejected, cancelled, or expired registrations cannot be edited.',
            );
        }

        $this->profile = $profile;
    }

    public function isEditableByAdministrator(): bool
    {
        return in_array($this->status, [
            RegistrationStatus::Draft,
            RegistrationStatus::Submitted,
            RegistrationStatus::UnderReview,
            RegistrationStatus::CorrectionRequested,
        ], true);
    }

    public function isArchivableByAdministrator(): bool
    {
        return in_array($this->status, [
            RegistrationStatus::Draft,
            RegistrationStatus::Rejected,
            RegistrationStatus::Cancelled,
            RegistrationStatus::Expired,
        ], true);
    }

    public function submit(TenantId $tenantId, DateTimeImmutable $occurredAt): void
    {
        if ($this->status !== RegistrationStatus::Draft) {
            throw new InvalidClinicRegistrationTransitionException('Only draft registrations may be submitted.');
        }

        if (! $this->profile->isSubmittable() || $this->declarations === [] || ! $this->commercialSelection->isSelected()) {
            throw new InvalidClinicRegistrationTransitionException('Registration is missing required submission information.');
        }

        if ($this->reservedTenantId !== null && $this->reservedTenantId->value !== $tenantId->value) {
            throw new InvalidClinicRegistrationTransitionException('Tenant id has already been reserved and cannot be replaced.');
        }

        $this->status = RegistrationStatus::Submitted;
        $this->reservedTenantId = $tenantId;
        $this->submittedAt = $occurredAt;
        $this->record(new ClinicRegistrationSubmitted(
            $this->id->value,
            $this->platformIdentity->value,
            $this->correlationReference,
            $occurredAt,
        ));
    }

    public function cancel(DateTimeImmutable $occurredAt): void
    {
        if (! in_array($this->status, [
            RegistrationStatus::Draft,
            RegistrationStatus::Submitted,
            RegistrationStatus::UnderReview,
            RegistrationStatus::CorrectionRequested,
        ], true)) {
            throw new InvalidClinicRegistrationTransitionException('Only active registrations may be cancelled.');
        }

        $this->status = RegistrationStatus::Cancelled;
        $this->cancelledAt = $occurredAt;
        $this->record(new ClinicRegistrationCancelled($this->id->value, $this->platformIdentity->value, $occurredAt));
    }

    public function expire(DateTimeImmutable $occurredAt): void
    {
        if (! in_array($this->status, [
            RegistrationStatus::Draft,
            RegistrationStatus::Submitted,
            RegistrationStatus::UnderReview,
            RegistrationStatus::CorrectionRequested,
        ], true)) {
            throw new InvalidClinicRegistrationTransitionException('Only active registrations may expire.');
        }

        $this->status = RegistrationStatus::Expired;
        $this->expiredAt = $occurredAt;
        $this->record(new ClinicRegistrationExpired($this->id->value, $this->platformIdentity->value, $occurredAt));
    }

    public function markProvisioned(?ProvisionedTenantReference $tenantReference, DateTimeImmutable $occurredAt): void
    {
        if ($this->status !== RegistrationStatus::Approved) {
            throw new InvalidClinicRegistrationTransitionException('Only approved registrations may be provisioned.');
        }

        $this->status = RegistrationStatus::Provisioned;
        $this->provisionedTenant = $tenantReference;
        $this->provisionedAt = $occurredAt;
        $this->record(new ClinicRegistrationProvisioned(
            $this->id->value,
            $this->platformIdentity->value,
            $this->correlationReference,
            $tenantReference?->value,
            $occurredAt,
        ));
    }

    public function startReview(string $reviewerPlatformIdentityId, DateTimeImmutable $occurredAt): void
    {
        if ($this->status !== RegistrationStatus::Submitted) {
            throw new InvalidClinicRegistrationTransitionException('Only submitted registrations may enter review.');
        }

        $this->status = RegistrationStatus::UnderReview;
        $this->record(new ClinicRegistrationReviewStarted($this->id->value, $reviewerPlatformIdentityId, $occurredAt));
    }

    public function decide(
        string $decisionId,
        RegistrationDecisionOutcome $outcome,
        string $reasonCategory,
        ?string $correctionInstructions,
        string $reviewerPlatformIdentityId,
        DateTimeImmutable $occurredAt,
    ): void {
        if ($this->status !== RegistrationStatus::UnderReview) {
            throw new InvalidClinicRegistrationTransitionException('Only registrations under review may receive a decision.');
        }

        $decision = new RegistrationDecision(
            $decisionId,
            $outcome,
            trim($reasonCategory),
            $correctionInstructions === null ? null : trim($correctionInstructions),
            $reviewerPlatformIdentityId,
            $occurredAt,
        );
        $this->decisions[] = $decision;
        $this->status = match ($outcome) {
            RegistrationDecisionOutcome::Approved => RegistrationStatus::Approved,
            RegistrationDecisionOutcome::Rejected => RegistrationStatus::Rejected,
            RegistrationDecisionOutcome::CorrectionRequested => RegistrationStatus::CorrectionRequested,
        };
        $this->record(new ClinicRegistrationDecisionRecorded(
            $this->id->value,
            $decisionId,
            $outcome->value,
            $reviewerPlatformIdentityId,
            $occurredAt,
        ));
    }

    /** @param list<DeclarationAcceptance> $declarations */
    public function resubmitCorrection(
        ClinicRegistrationProfile $profile,
        array $declarations,
        DateTimeImmutable $occurredAt,
    ): void {
        if ($this->status !== RegistrationStatus::CorrectionRequested) {
            throw new InvalidClinicRegistrationTransitionException('Only a registration awaiting correction may be resubmitted.');
        }

        if (! $profile->isSubmittable() || $declarations === []) {
            throw new InvalidClinicRegistrationTransitionException('Corrected registration is missing required information.');
        }

        $this->profile = $profile;
        $this->declarations = $declarations;
        $current = $this->currentDecision();
        if ($current === null || $current->outcome !== RegistrationDecisionOutcome::CorrectionRequested) {
            throw new InvalidClinicRegistrationTransitionException('A current correction decision is required before resubmission.');
        }
        $current->supersede($occurredAt);
        $this->status = RegistrationStatus::UnderReview;
        $this->record(new ClinicRegistrationResubmitted($this->id->value, $occurredAt));
    }

    public function currentDecision(): ?RegistrationDecision
    {
        foreach (array_reverse($this->decisions) as $decision) {
            if ($decision->supersededAt === null) {
                return $decision;
            }
        }

        return null;
    }

    public function assertOwnedBy(PlatformIdentityReference $platformIdentity): void
    {
        if ($this->platformIdentity->value !== $platformIdentity->value) {
            throw new InvalidClinicRegistrationTransitionException('Clinic registration does not belong to this platform identity.');
        }
    }

    public function version(): int
    {
        return $this->version;
    }

    public function synchronizeVersion(int $version): void
    {
        $this->version = $version;
    }

    /** @return list<object> */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    private function assertMutableDraft(): void
    {
        if ($this->status !== RegistrationStatus::Draft) {
            throw new InvalidClinicRegistrationTransitionException('Only draft registrations may be updated.');
        }
    }

    private function record(object $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
