<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Provider configuration administration is not a Payment lifecycle
 * transition, so this deliberately does not go through PaymentAuditInterface
 * (which is shaped around the Payment aggregate). It reuses the same
 * platform-wide AuditEntryRecorderInterface every other privileged platform
 * action already records through (mirrors CommercialOfferAuditTrail and
 * ClinicRegistrationAuditTrail's existing, approved convention).
 */
final readonly class PaymentProviderAuditTrail
{
    private const string TARGET_TYPE = 'payment_provider_configuration';

    public function __construct(private AuditEntryRecorderInterface $auditEntries) {}

    /**
     * @param  array<string, bool>  $priorState
     * @param  array<string, bool>  $resultingState
     */
    public function record(
        string $action,
        string $providerKey,
        array $priorState,
        array $resultingState,
        ?string $platformIdentityId,
        AuditOutcomeType $outcome,
        ?string $reasonCode,
        DateTimeImmutable $occurredAt,
        string $correlationId,
    ): void {
        $occurredAt = $occurredAt->setTimezone(new DateTimeZone('UTC'));

        $this->auditEntries->record(new AuditEntryData(
            auditEntryId: $this->auditEntryId($action, $providerKey, $occurredAt, $correlationId),
            occurredAt: $occurredAt,
            actor: $platformIdentityId === null
                ? new AuditActorData(AuditActorType::Anonymous->value, null)
                : new AuditActorData(AuditActorType::PlatformIdentity->value, $platformIdentityId),
            tenantId: null,
            action: $action,
            target: new AuditTargetData(self::TARGET_TYPE, $providerKey),
            outcome: new AuditOutcomeData($outcome->value, $reasonCode),
            correlationId: $correlationId,
            safeMetadata: [
                'resource_type' => self::TARGET_TYPE,
                'resource_label' => $this->stateLabel($priorState),
                'target_label' => $this->stateLabel($resultingState),
            ],
        ));
    }

    /** @param  array<string, bool>  $state */
    private function stateLabel(array $state): string
    {
        $parts = [];
        foreach ($state as $field => $value) {
            $parts[] = $field.'='.($value ? '1' : '0');
        }

        return implode(',', $parts);
    }

    private function auditEntryId(string $action, string $targetId, DateTimeImmutable $occurredAt, string $correlationId): string
    {
        $hex = substr(hash('sha256', implode('|', [$action, $targetId, $occurredAt->format('c'), $correlationId])), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
