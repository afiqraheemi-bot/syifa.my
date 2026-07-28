<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Infrastructure;

use App\Modules\ClinicRegistration\Contracts\Review\ClinicRegistrationReviewAuditInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;

final readonly class ClinicRegistrationPlatformAuditAdapter implements ClinicRegistrationReviewAuditInterface
{
    public function __construct(private AuditEntryRecorderInterface $audit) {}

    public function record(
        string $auditEntryId,
        string $actorPlatformIdentityId,
        string $registrationId,
        string $action,
        string $outcome,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->audit->record(new AuditEntryData(
            $auditEntryId,
            $occurredAt,
            new AuditActorData(AuditActorType::PlatformIdentity->value, $actorPlatformIdentityId),
            null,
            $action,
            new AuditTargetData('clinic_registration', $registrationId),
            new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
            $correlationId,
            [
                'resource_type' => 'clinic_registration_decision',
                'target_label' => sprintf('outcome=%s;version=%d', $outcome, $resultingVersion),
            ],
        ));
    }
}
