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

final readonly class PaymentVerificationFinancialAuditTrail
{
    public function __construct(private AuditEntryRecorderInterface $audit) {}

    /** @param array<string, bool|float|int|string> $metadata */
    public function record(string $action, string $paymentId, string $correlationId, DateTimeImmutable $occurredAt, array $metadata): void
    {
        $this->audit->record(new AuditEntryData(
            auditEntryId: $this->id($action, $paymentId, $correlationId), occurredAt: $occurredAt,
            actor: new AuditActorData(AuditActorType::System->value, null), tenantId: null, action: $action,
            target: new AuditTargetData('payment', $paymentId),
            outcome: new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null), correlationId: $correlationId,
            safeMetadata: [
                'resource_type' => 'payment_verification',
                'resource_label' => (string) ($metadata['result_code'] ?? 'unknown'),
                'target_label' => sprintf(
                    'receipt_id=%s;provider_key=%s;attempt_reference=%s',
                    (string) ($metadata['receipt_id'] ?? ''),
                    (string) ($metadata['provider_key'] ?? ''),
                    (string) ($metadata['attempt_reference'] ?? ''),
                ),
            ],
        ));
    }

    private function id(string $action, string $paymentId, string $correlationId): string
    {
        $hex = substr(hash('sha256', $action.'|'.$paymentId.'|'.$correlationId), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 3) | 8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
