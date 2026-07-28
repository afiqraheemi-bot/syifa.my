<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\ClinicContact;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\WebsiteBuilder\Application\Exceptions\ClinicNotFoundException;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Transactions\ClinicTransactionInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleClinicWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicContactProfile;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use DateTimeImmutable;
use DateTimeZone;

final readonly class UpdateClinicContactProfileService
{
    public function __construct(
        private ClinicRepositoryInterface $clinics,
        private ClinicTransactionInterface $transactions,
        private WebsiteAuthorization $authorization,
        private AuditEntryRecorderInterface $auditEntries,
    ) {}

    public function read(string $tenantId, WebsiteAuthorizationContext $authorization): ClinicContactProfileData
    {
        $tenant = new TenantId($tenantId);
        $this->authorization->assertCanUpdate($authorization, $tenant);
        $clinic = $this->clinics->findByTenantId($tenant)
            ?? throw new ClinicNotFoundException('Clinic was not found in the authorized Tenant.');

        return ClinicContactProfileData::fromClinic($clinic);
    }

    public function handle(UpdateClinicContactProfileCommand $command): UpdateClinicContactProfileResult
    {
        $tenantId = new TenantId($command->tenantId);
        $clinicId = new ClinicId($command->clinicId);
        $this->authorization->assertCanUpdate($command->authorization, $tenantId);

        return $this->transactions->run(function () use ($command, $tenantId, $clinicId): UpdateClinicContactProfileResult {
            $clinic = $this->clinics->findById($tenantId, $clinicId);
            if ($clinic === null) {
                throw new ClinicNotFoundException('Clinic was not found in the authorized Tenant.');
            }
            if ($command->expectedVersion !== null && $clinic->version() !== $command->expectedVersion) {
                throw new StaleClinicWriteException('Clinic Contact Profile changed since it was loaded.');
            }

            $current = $clinic->contactProfile();
            $next = new ClinicContactProfile(
                $this->stringValue($command->operationalPhone, $current->operationalPhone),
                $this->stringValue($command->operationalEmail, $current->operationalEmail),
                $this->stringValue($command->postalAddress, $current->postalAddress),
                $this->stringValue($command->whatsAppNumber, $current->whatsAppNumber),
                $this->floatValue($command->latitude, $current->latitude),
                $this->floatValue($command->longitude, $current->longitude),
            );
            $changedFields = $current->changedFields($next);
            if (! $clinic->updateContactProfile($next, $command->occurredAt)) {
                return new UpdateClinicContactProfileResult(false, $current);
            }

            $this->clinics->save($clinic);
            $this->recordAudit($command, $changedFields, $next);

            return new UpdateClinicContactProfileResult(true, $next);
        });
    }

    private function stringValue(OptionalContactValue $input, ?string $current): ?string
    {
        if (! $input->supplied) {
            return $current;
        }
        if ($input->value === null || is_string($input->value)) {
            return $input->value;
        }
        throw new \InvalidArgumentException('Contact text input must be a string or null.');
    }

    private function floatValue(OptionalContactValue $input, ?float $current): ?float
    {
        if (! $input->supplied) {
            return $current;
        }
        if ($input->value === null || is_float($input->value)) {
            return $input->value;
        }
        throw new \InvalidArgumentException('Coordinate input must be a float or null.');
    }

    /** @param list<string> $changedFields */
    private function recordAudit(UpdateClinicContactProfileCommand $command, array $changedFields, ClinicContactProfile $profile): void
    {
        $occurredAt = $command->occurredAt->setTimezone(new DateTimeZone('UTC'));
        $actorType = $command->authorization->role === 'clinic_owner'
            ? AuditActorType::ClinicOwner
            : AuditActorType::PlatformIdentity;

        $this->auditEntries->record(new AuditEntryData(
            $this->auditId($command->clinicId, $occurredAt, $command->correlationId),
            $occurredAt,
            new AuditActorData($actorType->value, $command->authorization->actorId),
            $command->tenantId,
            'clinic.contact_profile.change',
            new AuditTargetData('clinic.contact_profile', $command->clinicId),
            new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
            $command->correlationId,
            [
                'actor_role' => $command->authorization->role,
                'category_key' => 'contact_profile',
                'resource_type' => 'clinic.contact_profile',
                'resource_label' => implode(',', $changedFields),
                'target_label' => sprintf(
                    'phone:%d,email:%d,address:%d,whatsapp:%d,coordinates:%d',
                    $profile->operationalPhone !== null,
                    $profile->operationalEmail !== null,
                    $profile->postalAddress !== null,
                    $profile->whatsAppNumber !== null,
                    $profile->latitude !== null,
                ),
            ],
        ));
    }

    private function auditId(string $clinicId, DateTimeImmutable $occurredAt, string $correlationId): string
    {
        $hex = substr(hash('sha256', implode('|', ['clinic.contact_profile.change', $clinicId, $occurredAt->format('c'), $correlationId])), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
