<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application\Administration;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\TenantManagement\Contracts\Administration\ClinicOwnerSetupLinkIssuerInterface;
use App\Modules\TenantManagement\Contracts\Administration\EstablishClinicOwnerCommand;
use App\Modules\TenantManagement\Contracts\Administration\EstablishedClinicOwnerData;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidClinicOwnerAuthorityTransitionException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerEmail;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentity;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerName;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use RuntimeException;

final readonly class EstablishClinicOwnerService
{
    public function __construct(
        private TenantRepositoryInterface $tenants,
        private AuditEntryRecorderInterface $audit,
        private ClinicOwnerSetupLinkIssuerInterface $setupLinks,
    ) {}

    public function execute(EstablishClinicOwnerCommand $command): string
    {
        $owner = $this->establish($command);

        if (! $this->setupLinks->issue($command->tenantId, (new ClinicOwnerEmail($command->email))->value)) {
            throw new RuntimeException('Clinic Owner setup email could not be issued.');
        }

        return $owner->authorityId;
    }

    public function executeForSelfRegistration(EstablishClinicOwnerCommand $command): EstablishedClinicOwnerData
    {
        return $this->establish($command);
    }

    private function establish(EstablishClinicOwnerCommand $command): EstablishedClinicOwnerData
    {
        $tenant = $this->tenants->find(new TenantId($command->tenantId));
        if ($tenant === null) {
            throw new InvalidClinicOwnerAuthorityTransitionException('Tenant is unavailable for Clinic Owner establishment.');
        }

        $email = new ClinicOwnerEmail($command->email);
        $existing = $tenant->activeClinicOwnerAuthority();
        if ($existing === null) {
            $authorityId = $this->identifier($command->tenantId, 'authority:'.$email->value);
            $tenant->establishClinicOwnerAuthority(
                new ClinicOwnerAuthorityId($authorityId),
                new ClinicOwnerIdentity(
                    new ClinicOwnerIdentityId($this->identifier($command->tenantId, 'identity:'.$email->value)),
                    $email,
                    new ClinicOwnerName($command->name),
                ),
                $command->occurredAt,
            );
            $tenant->activate($command->occurredAt);
            $this->tenants->save($tenant);
            $this->recordAudit($command, $authorityId);
        } else {
            if ($existing->clinicOwnerIdentity->email->value !== $email->value) {
                throw new InvalidClinicOwnerAuthorityTransitionException(
                    'Tenant already has a different active Clinic Owner Authority.',
                );
            }
            $authorityId = $existing->id->value;
        }

        $authority = $tenant->findClinicOwnerAuthority(new ClinicOwnerAuthorityId($authorityId));
        if ($authority === null) {
            throw new InvalidClinicOwnerAuthorityTransitionException('Clinic Owner authority is unavailable.');
        }

        return new EstablishedClinicOwnerData(
            $command->tenantId,
            $authorityId,
            $authority->clinicOwnerIdentity->id->value,
        );
    }

    private function recordAudit(EstablishClinicOwnerCommand $command, string $authorityId): void
    {
        $this->audit->record(new AuditEntryData(
            $this->identifier($authorityId, $command->correlationId),
            $command->occurredAt,
            new AuditActorData(AuditActorType::PlatformIdentity->value, $command->actorPlatformIdentityId),
            $command->tenantId,
            'tenant.clinic_owner.establish',
            new AuditTargetData('clinic_owner_authority', $authorityId),
            new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
            $command->correlationId,
            [
                'resource_type' => 'clinic_owner_authority',
                'target_label' => 'authority='.$authorityId,
            ],
        ));
    }

    private function identifier(string $left, string $right): string
    {
        $hex = substr(hash('sha256', $left.':'.$right), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
