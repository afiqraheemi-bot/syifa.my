<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\ClinicRegistration\Domain;

use App\Modules\ClinicRegistration\Domain\ClinicRegistration;
use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationProvisioned;
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
use PHPUnit\Framework\TestCase;

final class ClinicRegistrationTest extends TestCase
{
    public function test_start_binds_registration_to_platform_identity_reference(): void
    {
        $registration = $this->registration();

        self::assertSame(RegistrationStatus::Draft, $registration->status);
        self::assertSame($this->uuid(2), $registration->platformIdentity->value);
        self::assertSame($this->uuid(1), $registration->correlationReference);
        self::assertSame(0, $registration->version());
        self::assertContainsOnlyInstancesOf(ClinicRegistrationStarted::class, $registration->releaseEvents());
    }

    public function test_submit_requires_complete_profile_declaration_and_commercial_selection(): void
    {
        $registration = $this->registration();

        $this->expectException(InvalidClinicRegistrationTransitionException::class);

        $registration->submit($this->tenantId(), $this->occurredAt());
    }

    public function test_valid_draft_can_be_submitted(): void
    {
        $registration = $this->submittableRegistration();
        $registration->releaseEvents();

        $registration->submit($this->tenantId(), $this->occurredAt());

        self::assertSame(RegistrationStatus::Submitted, $registration->status);
        self::assertSame('2026-07-20T00:00:00+00:00', $registration->submittedAt?->format(DATE_ATOM));
        self::assertContainsOnlyInstancesOf(ClinicRegistrationSubmitted::class, $registration->releaseEvents());
    }

    public function test_submit_requires_the_address_needed_by_automatic_website_provisioning(): void
    {
        $registration = $this->registration();
        $registration->updateDraft(
            new ClinicRegistrationProfile('Klinik Syifa', 'owner@clinic.test', '+60123456789', null),
            [new DeclarationAcceptance('terms.acceptance', '2026-07-20', $this->occurredAt())],
            new CommercialSelectionReference('offering-basic-monthly', 'monthly', 'catalogue-v1'),
        );

        $this->expectException(InvalidClinicRegistrationTransitionException::class);

        $registration->submit($this->tenantId(), $this->occurredAt());
    }

    public function test_submit_reserves_the_supplied_tenant_id_exactly_once(): void
    {
        $registration = $this->submittableRegistration();

        self::assertNull($registration->reservedTenantId);

        $registration->submit($this->tenantId(), $this->occurredAt());

        self::assertSame($this->tenantId()->value, $registration->reservedTenantId?->value);
    }

    public function test_submit_does_not_generate_a_tenant_id_itself(): void
    {
        $registration = $this->submittableRegistration();

        $registration->submit($this->tenantId(), $this->occurredAt());

        self::assertSame($this->tenantId()->value, $registration->reservedTenantId?->value);
    }

    public function test_reserved_tenant_id_cannot_be_replaced_by_a_later_submission(): void
    {
        $registration = $this->submittableRegistration();
        $registration->submit($this->tenantId(), $this->occurredAt());

        $this->expectException(InvalidClinicRegistrationTransitionException::class);

        $registration->submit(new TenantId($this->uuid(3)), $this->occurredAt());
    }

    public function test_provisioned_is_the_terminal_completion_state(): void
    {
        $registration = $this->submittableRegistration();
        $registration->submit($this->tenantId(), $this->occurredAt());
        $this->approve($registration);
        $registration->releaseEvents();

        $registration->markProvisioned(new ProvisionedTenantReference('tenant-reference-1'), $this->occurredAt());

        self::assertSame(RegistrationStatus::Provisioned, $registration->status);
        self::assertTrue($registration->status->isTerminal());
        self::assertSame('tenant-reference-1', $registration->provisionedTenant?->value);
        self::assertSame($this->tenantId()->value, $registration->reservedTenantId?->value);
        self::assertContainsOnlyInstancesOf(ClinicRegistrationProvisioned::class, $registration->releaseEvents());
    }

    public function test_cancel_and_expire_are_rejected_after_provisioning(): void
    {
        $registration = $this->submittableRegistration();
        $registration->submit($this->tenantId(), $this->occurredAt());
        $this->approve($registration);
        $registration->markProvisioned(new ProvisionedTenantReference('tenant-reference-1'), $this->occurredAt());

        $this->expectException(InvalidClinicRegistrationTransitionException::class);

        $registration->cancel($this->occurredAt());
    }

    public function test_owner_isolation_rejects_cross_identity_substitution(): void
    {
        $registration = $this->registration();

        $this->expectException(InvalidClinicRegistrationTransitionException::class);

        $registration->assertOwnedBy(new PlatformIdentityReference($this->uuid(9)));
    }

    public function test_review_correction_resubmission_and_approval_are_governed(): void
    {
        $registration = $this->submittableRegistration();
        $registration->submit($this->tenantId(), $this->occurredAt());
        $registration->startReview($this->uuid(8), $this->occurredAt());
        $registration->decide(
            $this->uuid(9),
            RegistrationDecisionOutcome::CorrectionRequested,
            'contact_correction',
            'Confirm the operational phone number.',
            $this->uuid(8),
            $this->occurredAt(),
        );

        self::assertSame(RegistrationStatus::CorrectionRequested, $registration->status);

        $registration->resubmitCorrection(
            new ClinicRegistrationProfile('Klinik Syifa', 'owner@clinic.test', '+60129999999', '1 Jalan Klinik'),
            [new DeclarationAcceptance('terms.acceptance', '2026-07-20', $this->occurredAt())],
            $this->occurredAt()->modify('+1 minute'),
        );
        self::assertSame(RegistrationStatus::UnderReview, $registration->status);
        self::assertNotNull($registration->decisions[0]->supersededAt);

        $registration->decide(
            $this->uuid(10),
            RegistrationDecisionOutcome::Approved,
            'eligible_clinic',
            null,
            $this->uuid(8),
            $this->occurredAt()->modify('+2 minutes'),
        );
        self::assertSame(RegistrationStatus::Approved, $registration->status);
        self::assertSame(RegistrationDecisionOutcome::Approved, $registration->currentDecision()?->outcome);
    }

    public function test_version_can_be_synchronized_for_optimistic_concurrency(): void
    {
        $registration = $this->registration();

        $registration->synchronizeVersion(3);

        self::assertSame(3, $registration->version());
    }

    public function test_administrator_can_revise_active_registration_without_losing_website_preferences(): void
    {
        $registration = $this->registration();
        $registration->updateDraft(
            new ClinicRegistrationProfile(
                'Klinik Syifa',
                'owner@clinic.test',
                '+60123456789',
                '1 Jalan Klinik',
                'klinik-syifa',
                'SYIFA_CARE',
            ),
            [new DeclarationAcceptance('terms.acceptance', '2026-07-20', $this->occurredAt())],
            new CommercialSelectionReference('offering-basic-monthly', 'monthly', 'catalogue-v1'),
        );
        $registration->submit($this->tenantId(), $this->occurredAt());

        $registration->reviseProfileByAdministrator(new ClinicRegistrationProfile(
            'Klinik Syifa Utama',
            'updated@clinic.test',
            '+60129999999',
            '2 Jalan Klinik',
            $registration->profile->preferredSubdomain,
            $registration->profile->selectedWebsiteTemplate,
        ));

        self::assertSame('Klinik Syifa Utama', $registration->profile->clinicName);
        self::assertSame('klinik-syifa', $registration->profile->preferredSubdomain);
        self::assertSame('SYIFA_CARE', $registration->profile->selectedWebsiteTemplate);
        self::assertSame(RegistrationStatus::Submitted, $registration->status);
    }

    public function test_administrator_cannot_revise_an_approved_registration(): void
    {
        $registration = $this->submittableRegistration();
        $registration->submit($this->tenantId(), $this->occurredAt());
        $this->approve($registration);

        $this->expectException(InvalidClinicRegistrationTransitionException::class);

        $registration->reviseProfileByAdministrator(new ClinicRegistrationProfile(
            'Changed clinic',
            'changed@clinic.test',
            '+60129999999',
            '2 Jalan Klinik',
        ));
    }

    public function test_only_pre_financial_terminal_registrations_can_be_archived(): void
    {
        $draft = $this->registration();
        self::assertTrue($draft->isArchivableByAdministrator());

        $submitted = $this->submittableRegistration();
        $submitted->submit($this->tenantId(), $this->occurredAt());

        self::assertFalse($submitted->isArchivableByAdministrator());
        self::assertTrue($submitted->isEditableByAdministrator());
    }

    private function submittableRegistration(): ClinicRegistration
    {
        $registration = $this->registration();
        $registration->updateDraft(
            new ClinicRegistrationProfile('Klinik Syifa', 'owner@clinic.test', '+60123456789', '1 Jalan Klinik'),
            [new DeclarationAcceptance('terms.acceptance', '2026-07-20', $this->occurredAt())],
            new CommercialSelectionReference('offering-basic-monthly', 'monthly', 'catalogue-v1'),
        );

        return $registration;
    }

    private function registration(): ClinicRegistration
    {
        return ClinicRegistration::start(
            new RegistrationId($this->uuid(1)),
            new PlatformIdentityReference($this->uuid(2)),
            $this->occurredAt(),
        );
    }

    private function approve(ClinicRegistration $registration): void
    {
        $registration->startReview($this->uuid(8), $this->occurredAt());
        $registration->decide(
            $this->uuid(9),
            RegistrationDecisionOutcome::Approved,
            'eligible_clinic',
            null,
            $this->uuid(8),
            $this->occurredAt(),
        );
    }

    private function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-20T00:00:00Z');
    }

    private function tenantId(): TenantId
    {
        return new TenantId($this->uuid(4));
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
