<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Infrastructure;

use App\Modules\Booking\Contracts\ServiceSetup\ServiceSetupAuditInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use Illuminate\Support\Str;

final readonly class BookingServiceSetupPlatformAuditAdapter implements ServiceSetupAuditInterface
{
    public function __construct(private AuditEntryRecorderInterface $audit) {}

    public function record(string $tenantId, string $actorId, string $correlationId, string $action, string $serviceId, array $metadata): void
    {
        $safeMetadata = [];
        foreach ($metadata as $key => $value) {
            if ($value !== null) {
                $safeMetadata[$key] = $value;
            }
        }

        $this->audit->record(new AuditEntryData(
            (string) Str::uuid(),
            new DateTimeImmutable,
            new AuditActorData(AuditActorType::ClinicOwner->value, $actorId),
            $tenantId,
            $action,
            new AuditTargetData('clinic_service', $serviceId),
            new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
            $correlationId,
            $safeMetadata,
        ));
    }
}
