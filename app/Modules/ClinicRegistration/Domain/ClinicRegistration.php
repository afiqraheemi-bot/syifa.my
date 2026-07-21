<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Domain;

use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationCancelled;
use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationExpired;
use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationProvisioned;
use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationStarted;
use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationSubmitted;
use App\Modules\ClinicRegistration\Domain\Exceptions\InvalidClinicRegistrationTransitionException;
use App\Modules\ClinicRegistration\Domain\ValueObjects\ClinicRegistrationProfile;
use App\Modules\ClinicRegistration\Domain\ValueObjects\CommercialSelectionReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\DeclarationAcceptance;
use App\Modules\ClinicRegistration\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\ProvisionedTenantReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationId;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationStatus;
use App\Modules\ClinicRegistration\Domain\ValueObjects\TenantId;
use DateTimeImmutable;

final class ClinicRegistration
{
    /**
     * @param  list<DeclarationAcceptance>  $declarations
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
        if (! in_array($this->status, [RegistrationStatus::Draft, RegistrationStatus::Submitted], true)) {
            throw new InvalidClinicRegistrationTransitionException('Only active registrations may be cancelled.');
        }

        $this->status = RegistrationStatus::Cancelled;
        $this->cancelledAt = $occurredAt;
        $this->record(new ClinicRegistrationCancelled($this->id->value, $this->platformIdentity->value, $occurredAt));
    }

    public function expire(DateTimeImmutable $occurredAt): void
    {
        if (! in_array($this->status, [RegistrationStatus::Draft, RegistrationStatus::Submitted], true)) {
            throw new InvalidClinicRegistrationTransitionException('Only active registrations may expire.');
        }

        $this->status = RegistrationStatus::Expired;
        $this->expiredAt = $occurredAt;
        $this->record(new ClinicRegistrationExpired($this->id->value, $this->platformIdentity->value, $occurredAt));
    }

    public function markProvisioned(?ProvisionedTenantReference $tenantReference, DateTimeImmutable $occurredAt): void
    {
        if ($this->status !== RegistrationStatus::Submitted) {
            throw new InvalidClinicRegistrationTransitionException('Only submitted registrations may be provisioned.');
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
