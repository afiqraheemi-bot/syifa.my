<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\CapabilityDefinition;
use DateTimeImmutable;
use DateTimeZone;

final readonly class CapabilityDefinitionAuditTrail
{
    private const string TARGET_TYPE = 'commercial_catalogue.capability_definition';

    public function __construct(private AuditEntryRecorderInterface $auditEntries) {}

    public function record(
        string $action,
        CapabilityDefinition $capabilityDefinition,
        int $previousVersion,
        string $previousStatus,
        string $occurredAt,
        string $actorPlatformIdentityId,
        string $correlationId,
    ): void {
        $this->auditEntries->record(new AuditEntryData(
            self::auditEntryId($action, $occurredAt, $actorPlatformIdentityId, $correlationId, $capabilityDefinition->id->value),
            new DateTimeImmutable($occurredAt, new DateTimeZone('UTC')),
            new AuditActorData(AuditActorType::PlatformIdentity->value, $actorPlatformIdentityId),
            null,
            $action,
            new AuditTargetData(self::TARGET_TYPE, $capabilityDefinition->id->value),
            new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
            $correlationId,
            [
                'resource_type' => self::TARGET_TYPE,
                'resource_label' => $capabilityDefinition->key->value,
                'target_label' => sprintf(
                    'previous_version=%d;resulting_version=%d;previous_status=%s;resulting_status=%s',
                    $previousVersion,
                    $capabilityDefinition->version(),
                    $previousStatus,
                    $capabilityDefinition->status->value,
                ),
            ],
        ));
    }

    private static function auditEntryId(
        string $action,
        string $occurredAt,
        string $actorPlatformIdentityId,
        string $correlationId,
        string $targetId,
    ): string {
        $hex = substr(hash('sha256', implode('|', [$action, $targetId, $occurredAt, $actorPlatformIdentityId, $correlationId])), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
