<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Infrastructure;

use App\Modules\Onboarding\Contracts\Administration\OnboardingAuditInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;

final readonly class OnboardingPlatformAuditAdapter implements OnboardingAuditInterface
{
    public function __construct(private AuditEntryRecorderInterface $audit) {}

    public function recordDesignerAssignment(
        string $auditEntryId,
        string $actorPlatformIdentityId,
        string $tenantId,
        string $jobId,
        string $assignmentId,
        string $designerId,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->audit->record(new AuditEntryData(
            $auditEntryId,
            $occurredAt,
            new AuditActorData(AuditActorType::PlatformIdentity->value, $actorPlatformIdentityId),
            $tenantId,
            'onboarding.website_designer.assign',
            new AuditTargetData('onboarding_job', $jobId),
            new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
            $correlationId,
            [
                'resource_type' => 'website_designer_assignment',
                'target_label' => sprintf(
                    'assignment=%s;designer=%s;version=%d',
                    $assignmentId,
                    $designerId,
                    $resultingVersion,
                ),
            ],
        ));
    }
}
