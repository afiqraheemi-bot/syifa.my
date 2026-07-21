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

    public function test_version_can_be_synchronized_for_optimistic_concurrency(): void
    {
        $registration = $this->registration();

        $registration->synchronizeVersion(3);

        self::assertSame(3, $registration->version());
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
