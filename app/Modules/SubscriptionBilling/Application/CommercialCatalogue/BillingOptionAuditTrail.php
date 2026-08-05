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
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\BillingOption;
use DateTimeImmutable;
use DateTimeZone;

final readonly class BillingOptionAuditTrail
{
    private const string TARGET_TYPE = 'commercial_catalogue.billing_option';

    public function __construct(private AuditEntryRecorderInterface $auditEntries) {}

    public function record(
        string $action,
        BillingOption $billingOption,
        int $previousVersion,
        string $occurredAt,
        string $actorPlatformIdentityId,
        string $correlationId,
    ): void {
        $this->auditEntries->record(new AuditEntryData(
            self::auditEntryId($action, $occurredAt, $actorPlatformIdentityId, $correlationId, $billingOption->id->value),
            new DateTimeImmutable($occurredAt, new DateTimeZone('UTC')),
            new AuditActorData(AuditActorType::PlatformIdentity->value, $actorPlatformIdentityId),
            null,
            $action,
            new AuditTargetData(self::TARGET_TYPE, $billingOption->id->value),
            new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
            $correlationId,
            [
                'resource_type' => self::TARGET_TYPE,
                'resource_label' => $billingOption->code->value,
                'target_label' => sprintf(
                    'previous_version=%d;resulting_version=%d;availability=%s;recurrence=%s',
                    $previousVersion,
                    $billingOption->version(),
                    $billingOption->availability->value,
                    $billingOption->recurrence->value,
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
