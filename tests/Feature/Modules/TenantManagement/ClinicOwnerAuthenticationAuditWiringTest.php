<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\TenantManagement;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticatedPrincipal;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationCommand;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationInterface;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationOutcome;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationResult;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationRejected;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationSucceeded;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextData;
use Tests\TestCase;

/**
 * Sprint 3A Phase 5: proves the real, container-wired event dispatcher —
 * not the listener class in isolation — actually delivers Clinic Owner
 * authentication outcomes to the audit trail end to end, through the exact
 * same `POST /sessions` path a real login uses.
 */
final class ClinicOwnerAuthenticationAuditWiringTest extends TestCase
{
    public function test_a_real_login_through_the_http_endpoint_is_audited_via_the_real_event_dispatcher(): void
    {
        config()->set('session.driver', 'array');
        $recorder = new AuditWiringTrackingRecorder;
        $this->app->instance(AuditEntryRecorderInterface::class, $recorder);
        $this->app->bind(ClinicOwnerAuthenticationInterface::class, static fn (): ClinicOwnerAuthenticationInterface => new AuditWiringSuccessfulAuthentication);

        $this->postJson('https://clinic.app.syifa.my/api/v1/sessions', [
            'email' => 'owner@example.test',
            'password' => 'a private passphrase',
        ])->assertCreated();

        self::assertCount(1, $recorder->entries);
        self::assertSame('clinic_owner.authentication.login', $recorder->entries[0]->action);
        self::assertSame(AuditOutcomeType::Succeeded->value, $recorder->entries[0]->outcome->outcome);
        self::assertSame(AuditActorType::ClinicOwner->value, $recorder->entries[0]->actor->type);
    }

    public function test_a_rejected_login_through_the_http_endpoint_is_audited_as_failed(): void
    {
        config()->set('session.driver', 'array');
        $recorder = new AuditWiringTrackingRecorder;
        $this->app->instance(AuditEntryRecorderInterface::class, $recorder);
        $this->app->bind(ClinicOwnerAuthenticationInterface::class, static fn (): ClinicOwnerAuthenticationInterface => new AuditWiringRejectedAuthentication);

        $this->postJson('https://clinic.app.syifa.my/api/v1/sessions', [
            'email' => 'owner@example.test',
            'password' => 'wrong',
        ])->assertUnauthorized();

        self::assertCount(1, $recorder->entries);
        self::assertSame(AuditOutcomeType::Failed->value, $recorder->entries[0]->outcome->outcome);
        self::assertSame(AuditActorType::Anonymous->value, $recorder->entries[0]->actor->type);
    }
}

final class AuditWiringTrackingRecorder implements AuditEntryRecorderInterface
{
    /** @var list<AuditEntryData> */
    public array $entries = [];

    public function record(AuditEntryData $auditEntry): AuditEntry
    {
        $this->entries[] = $auditEntry;

        return AuditEntry::record(
            new AuditEntryId($auditEntry->auditEntryId),
            $auditEntry->occurredAt,
            AuditActorType::from($auditEntry->actor->type),
            $auditEntry->actor->identityId,
            $auditEntry->tenantId,
            $auditEntry->action,
            $auditEntry->target->type,
            $auditEntry->target->id,
            AuditOutcomeType::from($auditEntry->outcome->outcome),
            $auditEntry->outcome->reasonCode,
            $auditEntry->correlationId,
            $auditEntry->safeMetadata,
        );
    }
}

final class AuditWiringSuccessfulAuthentication implements ClinicOwnerAuthenticationInterface
{
    public function authenticate(ClinicOwnerAuthenticationCommand $command): ClinicOwnerAuthenticationResult
    {
        $principal = new ClinicOwnerAuthenticatedPrincipal(
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000003',
        );

        return new ClinicOwnerAuthenticationResult(
            ClinicOwnerAuthenticationOutcome::Authenticated,
            $principal,
            new TenantContextData(null, $principal->tenantId, 'clinic_owner', null),
            new ClinicOwnerAuthenticationSucceeded(
                $principal->tenantId,
                $principal->authorityId,
                $principal->clinicOwnerIdentityId,
                $command->attemptedAt,
            ),
        );
    }
}

final class AuditWiringRejectedAuthentication implements ClinicOwnerAuthenticationInterface
{
    public function authenticate(ClinicOwnerAuthenticationCommand $command): ClinicOwnerAuthenticationResult
    {
        return new ClinicOwnerAuthenticationResult(
            ClinicOwnerAuthenticationOutcome::Rejected,
            null,
            null,
            new ClinicOwnerAuthenticationRejected($command->attemptedAt),
        );
    }
}
