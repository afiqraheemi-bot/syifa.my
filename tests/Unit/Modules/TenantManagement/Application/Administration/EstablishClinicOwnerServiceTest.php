<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TenantManagement\Application\Administration;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\TenantManagement\Application\Administration\EstablishClinicOwnerService;
use App\Modules\TenantManagement\Contracts\Administration\ClinicOwnerSetupLinkIssuerInterface;
use App\Modules\TenantManagement\Contracts\Administration\EstablishClinicOwnerCommand;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EstablishClinicOwnerServiceTest extends TestCase
{
    public function test_super_admin_establishes_audited_authority_and_issues_owner_controlled_setup(): void
    {
        $tenant = Tenant::provision(new TenantId($this->uuid(1)), new DateTimeImmutable('2026-09-01T00:00:00Z'));
        $repository = new InMemoryOwnerTenantRepository($tenant);
        $links = new RecordingClinicOwnerSetupLinkIssuer;
        $audit = new RecordingOwnerAudit;
        $service = new EstablishClinicOwnerService($repository, $audit, $links);

        $authorityId = $service->execute(new EstablishClinicOwnerCommand(
            $tenant->id->value,
            'Aisyah Rahman',
            'OWNER@CLINIC.TEST',
            $this->uuid(2),
            $this->uuid(3),
            new DateTimeImmutable('2026-09-01T00:01:00Z'),
        ));

        self::assertSame($authorityId, $tenant->activeClinicOwnerAuthority()?->id->value);
        self::assertSame('owner@clinic.test', $tenant->activeClinicOwnerAuthority()?->clinicOwnerIdentity->email->value);
        self::assertSame('active', $tenant->status()->value);
        self::assertSame([$tenant->id->value.':owner@clinic.test'], $links->emails);
        self::assertSame('tenant.clinic_owner.establish', $audit->entries[0]->action);
        self::assertSame(1, $repository->saveCalls);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final class InMemoryOwnerTenantRepository implements TenantRepositoryInterface
{
    public int $saveCalls = 0;

    public function __construct(private Tenant $tenant) {}

    public function find(TenantId $tenantId): ?Tenant
    {
        return $this->tenant->id->value === $tenantId->value ? $this->tenant : null;
    }

    public function save(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->saveCalls++;
    }
}

final class RecordingClinicOwnerSetupLinkIssuer implements ClinicOwnerSetupLinkIssuerInterface
{
    /** @var list<string> */
    public array $emails = [];

    public function issue(string $tenantId, string $email): bool
    {
        $this->emails[] = $tenantId.':'.$email;

        return true;
    }
}

final class RecordingOwnerAudit implements AuditEntryRecorderInterface
{
    /** @var list<AuditEntry> */
    public array $entries = [];

    public function record(AuditEntryData $auditEntry): AuditEntry
    {
        $entry = AuditEntry::record(
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
        $this->entries[] = $entry;

        return $entry;
    }
}
